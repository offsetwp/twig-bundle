<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Unit\DependencyInjection\Compiler
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Unit\DependencyInjection\Compiler;

use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\LoaderPass;
use OffsetWP\Bundle\TwigBundle\Loader\NoTemplateSourceLoader;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\MemoryLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Environment;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;

/**
 * The pass that decides which loader the environment receives.
 */
#[CoversClass( LoaderPass::class )]
final class LoaderPassTest extends TestCase {
	/**
	 * A container holding what the extension would have put there: an environment, and
	 * the stand-in loader for a project with no template source at all.
	 *
	 * @return ContainerBuilder
	 */
	private function container(): ContainerBuilder {
		$container = new ContainerBuilder();
		$container->register( Environment::class, Environment::class );
		$container->register( NoTemplateSourceLoader::class, NoTemplateSourceLoader::class )
			->setArguments( array( '/nowhere/templates' ) );

		return $container;
	}

	/**
	 * The id the loader interface points at after the pass has run.
	 *
	 * @param ContainerBuilder $container The processed container.
	 * @return string
	 */
	private function resolvedLoader( ContainerBuilder $container ): string {
		$this->assertTrue( $container->hasAlias( LoaderInterface::class ) );

		return (string) $container->getAlias( LoaderInterface::class );
	}

	/**
	 * Every pass of this bundle begins by asking whether there is an environment to
	 * work on, because a container is compiled whether or not this bundle is in it —
	 * the kernel of a project that never registered it still runs every pass a bundle
	 * put there in a previous request of the same process.
	 *
	 * @return void
	 */
	public function testItDoesNothingWhenThereIsNoEnvironmentToConfigure(): void {
		$container = new ContainerBuilder();
		$container->register( 'app.loader', MemoryLoader::class )->addTag( LoaderPass::LOADER_TAG );

		( new LoaderPass() )->process( $container );

		$this->assertFalse( $container->hasAlias( LoaderInterface::class ) );
		$this->assertFalse( $container->hasDefinition( LoaderPass::CHAIN_ID ) );
	}

	/**
	 * A service tagged as a loader whose class cannot load anything is refused here
	 * rather than at the first render, where the failure would be a type error from
	 * inside Twig naming nothing a reader can act on.
	 *
	 * @return void
	 */
	public function testAServiceTaggedAsALoaderThatIsNotOneIsRefused(): void {
		$container = $this->container();
		$container->register( 'app.not_a_loader', \stdClass::class )->addTag( LoaderPass::LOADER_TAG );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage(
			'The service "app.not_a_loader" is tagged "twig.loader" but its class "stdClass" does not implement "Twig\\Loader\\LoaderInterface".'
		);

		( new LoaderPass() )->process( $container );
	}

	/**
	 * One loader is used as it is. A chain of one would only lengthen every stack
	 * trace and blur Twig's own "looked into" message for nothing.
	 *
	 * @return void
	 */
	public function testASingleLoaderIsUsedWithoutAChain(): void {
		$container = $this->container();
		$container->register( 'app.loader', MemoryLoader::class )->addTag( LoaderPass::LOADER_TAG );

		( new LoaderPass() )->process( $container );

		$this->assertSame( 'app.loader', $this->resolvedLoader( $container ) );
		$this->assertFalse( $container->hasDefinition( LoaderPass::CHAIN_ID ) );
	}

	/**
	 * Same tag twice on one loader — autoconfiguration adds it, the host adds it again
	 * to set a priority. Counted twice, the single loader was wrapped in a chain with
	 * itself, consulted once above the filesystem and once below it.
	 *
	 * @return void
	 */
	public function testALoaderCarryingTheTagTwiceIsNotChainedWithItself(): void {
		$container = $this->container();
		$container->register( 'app.loader', MemoryLoader::class )
			->addTag( LoaderPass::LOADER_TAG, array( 'priority' => 10 ) )
			->addTag( LoaderPass::LOADER_TAG );

		( new LoaderPass() )->process( $container );

		$this->assertSame( 'app.loader', $this->resolvedLoader( $container ) );
		$this->assertFalse( $container->hasDefinition( LoaderPass::CHAIN_ID ) );
	}

	/**
	 * Several are chained, highest priority first, so a host loader can be consulted
	 * before the filesystem rather than after it.
	 *
	 * @return void
	 */
	public function testSeveralLoadersAreChainedInPriorityOrder(): void {
		$container = $this->container();
		$container->register( 'app.low', MemoryLoader::class )->addTag( LoaderPass::LOADER_TAG );
		$container->register( 'app.high', MemoryLoader::class )
			->addTag( LoaderPass::LOADER_TAG, array( 'priority' => 10 ) );

		( new LoaderPass() )->process( $container );

		$this->assertSame( LoaderPass::CHAIN_ID, $this->resolvedLoader( $container ) );

		$chain = $container->getDefinition( LoaderPass::CHAIN_ID );

		$this->assertSame( ChainLoader::class, $chain->getClass() );

		$chained = $chain->getArgument( 0 );

		$this->assertIsArray( $chained );
		$this->assertSame( array( 'app.high', 'app.low' ), array_map( static fn ( mixed $reference ): string => $reference instanceof Reference ? (string) $reference : '', $chained ) );
	}

	/**
	 * Twig's own chain loader is a loader like any other, so a host can register it —
	 * autoconfiguration even tags it. The chain this pass builds used to be written
	 * under that same class name, which meant writing over the host's definition and
	 * then handing the result a reference to itself.
	 *
	 * @return void
	 */
	public function testAHostLoaderRegisteredUnderTheChainClassIsNotWrittenOver(): void {
		$container = $this->container();
		$container->register( ChainLoader::class, ChainLoader::class )->addTag( LoaderPass::LOADER_TAG );
		$container->register( 'app.loader', MemoryLoader::class )
			->addTag( LoaderPass::LOADER_TAG, array( 'priority' => 10 ) );

		( new LoaderPass() )->process( $container );

		$this->assertSame( LoaderPass::CHAIN_ID, $this->resolvedLoader( $container ) );
		$this->assertSame( ChainLoader::class, $container->getDefinition( ChainLoader::class )->getClass() );

		$chained = $container->getDefinition( LoaderPass::CHAIN_ID )->getArgument( 0 );

		$this->assertIsArray( $chained );
		$this->assertSame(
			array( 'app.loader', ChainLoader::class ),
			array_map( static fn ( mixed $reference ): string => $reference instanceof Reference ? (string) $reference : '', $chained )
		);
	}

	/**
	 * The interface id is this bundle's handle on whichever loader it settles on, not
	 * a slot a host fills. A definition sitting there was aliased to itself, which the
	 * library refuses with a message about a circular reference and nothing about Twig.
	 *
	 * @return void
	 */
	public function testALoaderRegisteredUnderTheInterfaceIdIsRefused(): void {
		$container = $this->container();
		$container->register( LoaderInterface::class, MemoryLoader::class )->addTag( LoaderPass::LOADER_TAG );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage(
			sprintf(
				'The loader "%s" is registered under the id "%s", which this bundle aliases to whichever loader it decides on.',
				MemoryLoader::class,
				LoaderInterface::class
			)
		);

		( new LoaderPass() )->process( $container );
	}

	/**
	 * A filesystem loader with no path answers every template with "there are no
	 * registered paths", which says nothing useful and, in a chain, would only
	 * lengthen the message. Untagging it is also what makes replacing it entirely a
	 * matter of configuring no path at all.
	 *
	 * The definition stays where it is. Removing it left a host service holding a
	 * reference to it pointing at nothing; untagged and unreferenced, the library's
	 * own pass drops it later anyway.
	 *
	 * @return void
	 */
	public function testTheFilesystemLoaderIsUntaggedWhenItHasNoPaths(): void {
		$without = $this->container();
		$without->register( FilesystemLoader::class, FilesystemLoader::class )->addTag( LoaderPass::LOADER_TAG );

		( new LoaderPass() )->process( $without );

		$this->assertTrue( $without->hasDefinition( FilesystemLoader::class ) );
		$this->assertFalse( $without->getDefinition( FilesystemLoader::class )->hasTag( LoaderPass::LOADER_TAG ) );
		$this->assertSame( NoTemplateSourceLoader::class, $this->resolvedLoader( $without ) );

		$with = $this->container();
		$with->register( FilesystemLoader::class, FilesystemLoader::class )
			->addMethodCall( 'addPath', array( '/somewhere', '__main__' ) )
			->addTag( LoaderPass::LOADER_TAG );

		( new LoaderPass() )->process( $with );

		$this->assertTrue( $with->hasDefinition( FilesystemLoader::class ) );
		$this->assertSame( FilesystemLoader::class, $this->resolvedLoader( $with ) );
	}

	/**
	 * "No path" is the absence of an addPath call, not the absence of every call. A
	 * host adding any other call to this definition was keeping a path-less loader
	 * alive, and with it the useless message this pass exists to avoid.
	 *
	 * @return void
	 */
	public function testACallThatIsNotAddPathDoesNotCountAsAPath(): void {
		$container = $this->container();
		$container->register( FilesystemLoader::class, FilesystemLoader::class )
			->addMethodCall( 'setPaths', array( array() ) )
			->addTag( LoaderPass::LOADER_TAG );

		( new LoaderPass() )->process( $container );

		$this->assertSame( NoTemplateSourceLoader::class, $this->resolvedLoader( $container ) );
	}
}
