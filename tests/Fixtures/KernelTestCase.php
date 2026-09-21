<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Fixtures;

use OffsetWP\Bundle\TwigBundle\Twig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Environment;

/**
 * What every integration test needs: a fixture project, a booted kernel, and the two
 * objects a test then asserts against.
 */
abstract class KernelTestCase extends TestCase {
	/**
	 * Booting a kernel registers its container with the facade, which is static and
	 * outlives the test. Nothing is carried in from the last one.
	 *
	 * Both ends, not just the one after. Clearing on the way out depends on every
	 * other test case in the suite doing the same, and the suite runs in one process
	 * in a random order; clearing on the way in depends on nothing.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Twig::reset();
	}

	/**
	 * And nothing is left behind for the next one either.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Twig::reset();

		parent::tearDown();
	}

	/**
	 * A fixture project directory.
	 *
	 * @param string $name The fixture project name.
	 * @return string
	 */
	protected function project( string $name = 'Project' ): string {
		return __DIR__ . DIRECTORY_SEPARATOR . $name;
	}

	/**
	 * A fixture project directory, resolved the way the kernel resolves its own root,
	 * so that a test can predict the value of the kernel root path parameter.
	 *
	 * @param string $name The fixture project name.
	 * @return string
	 */
	protected function rootPath( string $name = 'Project' ): string {
		$path = realpath( $this->project( $name ) );

		$this->assertIsString( $path );

		return $path;
	}

	/**
	 * A kernel on a fixture project, not booted yet, so that a test can change what
	 * the kernel itself reports before the container is built.
	 *
	 * @param array<string, mixed> $twig_config The configuration a host would write.
	 * @param \Closure|null        $services    Extra service definitions for this test.
	 * @param string               $name        The fixture project name.
	 * @return TestKernel
	 */
	protected function kernel( array $twig_config = array(), ?\Closure $services = null, string $name = 'Project' ): TestKernel {
		return new TestKernel( $this->project( $name ), $twig_config, $services );
	}

	/**
	 * Boot a kernel on a fixture project.
	 *
	 * @param array<string, mixed> $twig_config The configuration a host would write.
	 * @param \Closure|null        $services    Extra service definitions for this test.
	 * @param string               $name        The fixture project name.
	 * @return TestKernel
	 */
	protected function boot( array $twig_config = array(), ?\Closure $services = null, string $name = 'Project' ): TestKernel {
		$kernel = $this->kernel( $twig_config, $services, $name );
		$kernel->boot();

		return $kernel;
	}

	/**
	 * The environment of a booted kernel.
	 *
	 * @param TestKernel $kernel The booted kernel.
	 * @return Environment
	 */
	protected function twig( TestKernel $kernel ): Environment {
		$twig = $kernel->service( 'twig' );

		$this->assertInstanceOf( Environment::class, $twig );

		return $twig;
	}

	/**
	 * The compiled container of a booted kernel.
	 *
	 * @param TestKernel $kernel The booted kernel.
	 * @return ContainerBuilder
	 */
	protected function containerOf( TestKernel $kernel ): ContainerBuilder {
		$container = $kernel->container();

		$this->assertInstanceOf( ContainerBuilder::class, $container );

		return $container;
	}
}
