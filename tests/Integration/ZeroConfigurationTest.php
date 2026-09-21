<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Integration
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Integration;

use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\KernelTestCase;
use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\LoaderPass;
use OffsetWP\Bundle\TwigBundle\TwigBundle;
use OffsetWP\Framework\Bundle\BundleExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Environment;

/**
 * A real kernel, a real fixture project, and no configuration at all.
 */
#[CoversClass( TwigBundle::class )]
#[CoversClass( LoaderPass::class )]
final class ZeroConfigurationTest extends KernelTestCase {
	/**
	 * The definitions this bundle produces, before any compiler pass has run.
	 *
	 * A compiled container is not enough to see them: a private service that nothing
	 * references by id is inlined into its consumer, and its tags go with it.
	 *
	 * @return ContainerBuilder
	 */
	private function uncompiled(): ContainerBuilder {
		$container = new ContainerBuilder();
		$container->setParameter( 'kernel.root_path', $this->project() );

		$extension = ( new TwigBundle() )->getContainerExtension();

		$this->assertInstanceOf( BundleExtension::class, $extension );

		$extension->load( array( array() ), $container );

		return $container;
	}

	/**
	 * Every file under a directory, sorted, as absolute paths.
	 *
	 * @param string $directory The directory to walk.
	 * @return array<int, string>
	 */
	private function listing( string $directory ): array {
		$files = array();

		$walker = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $walker as $file ) {
			if ( $file instanceof \SplFileInfo ) {
				$files[] = $file->getPathname();
			}
		}

		sort( $files );

		return $files;
	}

	/**
	 * This bundle ships no cache system and no cache warmer, and the container is
	 * never dumped. Booting must therefore touch nothing on disk — not a directory,
	 * not a file, not the var/ tree the kernel would otherwise point a cache at.
	 *
	 * @return void
	 */
	public function testBootingTheKernelCreatesNoFilesOnDisk(): void {
		$before = $this->listing( $this->project() );

		$this->boot();

		$this->assertSame( $before, $this->listing( $this->project() ) );
		$this->assertDirectoryDoesNotExist( $this->project() . '/var' );
	}

	/**
	 * The whole promise of the package in one test: install the bundle, put a
	 * template in templates/, render it. No configuration file anywhere.
	 *
	 * @return void
	 */
	public function testATemplateRendersWithNoConfigurationAtAll(): void {
		$this->assertFileDoesNotExist( $this->project() . '/config/packages/twig.php' );

		$this->assertSame(
			"Hello, Jérôme!\n",
			$this->twig( $this->boot() )->render( 'hello.twig', array( 'name' => 'Jérôme' ) )
		);
	}

	/**
	 * Zero configuration works because the framework loads every registered extension
	 * with an empty configuration when the host wrote none. Nothing in this bundle
	 * makes that happen, so it is asserted here: the day it changes upstream, this
	 * test says so instead of a production site rendering nothing.
	 *
	 * @return void
	 */
	public function testTheExtensionIsLoadedEvenWhenTheHostWritesNoConfiguration(): void {
		$this->assertSame( array( array() ), $this->boot()->twigExtensionConfig() );
	}

	/**
	 * The container is compiled but never dumped, so a private service is reachable
	 * today and would stop being reachable the day that changes. Both ids the entry
	 * point resolves are therefore declared public, and that is asserted on the
	 * definitions themselves rather than on a get() that would succeed either way.
	 *
	 * @return void
	 */
	public function testTheTwigServiceAndItsClassAliasArePublic(): void {
		$container = $this->containerOf( $this->boot() );

		$this->assertTrue( $container->hasAlias( 'twig' ) );
		$this->assertTrue( $container->getAlias( 'twig' )->isPublic() );
		$this->assertTrue( $container->getDefinition( Environment::class )->isPublic() );
	}

	/**
	 * A service carrying "kernel.autoload" is built by a compiler pass while the
	 * container compiles, which happens on every request. Tagging anything of this
	 * bundle with it would construct the environment, its loader and every extension
	 * on a request that renders nothing at all.
	 *
	 * The uncompiled container is where a careless tag is actually visible, so it is
	 * asserted there too, and the twig.loader assertion proves that check is not
	 * vacuous.
	 *
	 * @return void
	 */
	public function testNoServiceOfThisBundleIsTaggedForEagerAutoloading(): void {
		$uncompiled = $this->uncompiled();

		$this->assertNotSame( array(), $uncompiled->findTaggedServiceIds( 'twig.loader' ) );
		$this->assertSame( array(), $uncompiled->findTaggedServiceIds( 'kernel.autoload' ) );
		$this->assertSame( array(), $this->containerOf( $this->boot() )->findTaggedServiceIds( 'kernel.autoload' ) );
	}
}
