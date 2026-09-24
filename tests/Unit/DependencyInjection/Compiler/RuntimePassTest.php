<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Unit\DependencyInjection\Compiler
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Unit\DependencyInjection\Compiler;

use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\RuntimePass;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\RatesRuntime;
use OffsetWP\Bundle\TwigBundle\TwigBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Environment;
use Twig\RuntimeLoader\ContainerRuntimeLoader;

/**
 * The pass that makes runtimes reachable without making them eager.
 */
#[CoversClass( RuntimePass::class )]
final class RuntimePassTest extends TestCase {
	/**
	 * A container holding nothing but an environment definition to append to.
	 *
	 * @return ContainerBuilder
	 */
	private function container(): ContainerBuilder {
		$container = new ContainerBuilder();
		$container->register( TwigBundle::ENVIRONMENT_ID, Environment::class );

		return $container;
	}

	/**
	 * The runtime loader the pass appended, if it appended one.
	 *
	 * @param ContainerBuilder $container The processed container.
	 * @return Definition|null
	 */
	private function runtimeLoader( ContainerBuilder $container ): ?Definition {
		foreach ( $container->getDefinition( TwigBundle::ENVIRONMENT_ID )->getMethodCalls() as $call ) {
			if ( ! is_array( $call ) || 'addRuntimeLoader' !== ( $call[0] ?? null ) ) {
				continue;
			}

			$argument = is_array( $call[1] ?? null ) ? ( $call[1][0] ?? null ) : null;

			if ( $argument instanceof Definition ) {
				return $argument;
			}
		}

		return null;
	}

	/**
	 * Twig asks a runtime loader for a class name, so the locator has to answer to
	 * class names. Getting this wrong fails at the first template call and nowhere
	 * earlier.
	 *
	 * @return void
	 */
	public function testItBuildsALocatorKeyedByClassName(): void {
		$container = $this->container();
		$container->register( 'app.rates', RatesRuntime::class )->addTag( RuntimePass::RUNTIME_TAG );

		( new RuntimePass() )->process( $container );

		$loader = $this->runtimeLoader( $container );

		$this->assertInstanceOf( Definition::class, $loader );
		$this->assertSame( ContainerRuntimeLoader::class, $loader->getClass() );

		$locator = $loader->getArgument( 0 );

		$this->assertInstanceOf( Reference::class, $locator );

		$map = $container->getDefinition( (string) $locator )->getArgument( 0 );

		$this->assertIsArray( $map );
		$this->assertSame( array( RatesRuntime::class ), array_keys( $map ) );
	}

	/**
	 * Same tag twice on one runtime — autoconfiguration adds it, the host adds it
	 * again to set a priority. Counted twice, the service was refused as two services
	 * for one runtime class, both of them itself.
	 *
	 * @return void
	 */
	public function testAServiceCarryingTheTagTwiceIsCountedOnce(): void {
		$container = $this->container();
		$container->register( 'app.rates', RatesRuntime::class )
			->addTag( RuntimePass::RUNTIME_TAG, array( 'priority' => 10 ) )
			->addTag( RuntimePass::RUNTIME_TAG );

		( new RuntimePass() )->process( $container );

		$loader = $this->runtimeLoader( $container );

		$this->assertInstanceOf( Definition::class, $loader );

		$locator = $loader->getArgument( 0 );

		$this->assertInstanceOf( Reference::class, $locator );

		$map = $container->getDefinition( (string) $locator )->getArgument( 0 );

		$this->assertIsArray( $map );
		$this->assertSame( array( RatesRuntime::class ), array_keys( $map ) );
	}

	/**
	 * A runtime declared as the child of another definition. The parent's class is
	 * copied down by a pass that runs after this one, so read raw the child is
	 * classless — and a runtime that is nothing but a parent and two arguments was
	 * refused as one built by a factory, with a message about factories and no factory
	 * anywhere in sight.
	 *
	 * @return void
	 */
	public function testARuntimeDeclaredAsAChildInheritsItsParentsClass(): void {
		$container = $this->container();
		$container->register( 'app.rates_base', RatesRuntime::class )->setAbstract( true );
		$container->setDefinition(
			'app.rates',
			( new ChildDefinition( 'app.rates_base' ) )->addTag( RuntimePass::RUNTIME_TAG )
		);

		( new RuntimePass() )->process( $container );

		$loader = $this->runtimeLoader( $container );

		$this->assertInstanceOf( Definition::class, $loader );

		$locator = $loader->getArgument( 0 );

		$this->assertInstanceOf( Reference::class, $locator );

		$map = $container->getDefinition( (string) $locator )->getArgument( 0 );

		$this->assertIsArray( $map );
		$this->assertSame( array( RatesRuntime::class ), array_keys( $map ) );
	}

	/**
	 * A container compiled without this bundle still runs its passes, so every one of
	 * them begins by asking whether there is an environment to work on.
	 *
	 * @return void
	 */
	public function testItDoesNothingWhenThereIsNoEnvironmentToConfigure(): void {
		$container = new ContainerBuilder();
		$container->register( 'app.rates', RatesRuntime::class )->addTag( RuntimePass::RUNTIME_TAG );

		( new RuntimePass() )->process( $container );

		$this->assertFalse( $container->hasDefinition( TwigBundle::ENVIRONMENT_ID ) );
	}

	/**
	 * A project using no runtime pays nothing: no locator, no loader, no call.
	 *
	 * @return void
	 */
	public function testItAddsNoRuntimeLoaderWhenNothingIsTagged(): void {
		$container = $this->container();

		( new RuntimePass() )->process( $container );

		$this->assertNull( $this->runtimeLoader( $container ) );
		$this->assertSame( array(), $container->getDefinition( TwigBundle::ENVIRONMENT_ID )->getMethodCalls() );
	}

	/**
	 * A locator holds references, which is all it needs. Making a runtime public
	 * would put it in the compiled container's public map for no reason.
	 *
	 * @return void
	 */
	public function testRuntimeServicesStayPrivate(): void {
		$container = $this->container();
		$container->register( 'app.rates', RatesRuntime::class )->addTag( RuntimePass::RUNTIME_TAG );

		( new RuntimePass() )->process( $container );

		$this->assertFalse( $container->getDefinition( 'app.rates' )->isPublic() );
	}

	/**
	 * Twig asks a runtime loader for a class name, so two services of one runtime class
	 * cannot both be given to it. Left alone the locator kept whichever came last and
	 * the other was simply never used — a choice nobody made and nobody could see.
	 *
	 * @return void
	 */
	public function testTheSameRuntimeClassFromTwoServicesIsRefused(): void {
		$container = $this->container();
		$container->register( 'app.rates', RatesRuntime::class )->addTag( RuntimePass::RUNTIME_TAG );
		$container->register( 'app.other_rates', RatesRuntime::class )->addTag( RuntimePass::RUNTIME_TAG );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage(
			sprintf(
				'The Twig runtime "%s" is registered as two services, "app.rates" and "app.other_rates". Twig asks for a runtime by class name and can only be handed one of them; keep one.',
				RatesRuntime::class
			)
		);

		( new RuntimePass() )->process( $container );
	}

	/**
	 * And a runtime with no class of its own cannot be looked up at all. It used to
	 * enter the locator under an empty key, where Twig would never come looking.
	 *
	 * @return void
	 */
	public function testARuntimeWithoutAClassIsRefused(): void {
		$container = $this->container();
		$container->register( 'app.built_by_a_factory' )->addTag( RuntimePass::RUNTIME_TAG );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage(
			'The service "app.built_by_a_factory" is tagged "twig.runtime" but has no class of its own. Twig asks a runtime loader for a class name, so a runtime built by a factory has to declare the class it builds.'
		);

		( new RuntimePass() )->process( $container );
	}
}
