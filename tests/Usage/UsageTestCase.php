<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Usage
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Usage;

use OffsetWP\Bundle\TwigBundle\Twig;
use OffsetWP\Framework\Kernel;
use OffsetWP\Support\Env;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

/**
 * What every usage test starts from: a whole project, booted the way a project boots.
 *
 * Nothing here reaches into the container, loads an extension by hand or injects a
 * configuration array. A project is a directory with a config/ directory in it, and
 * the kernel is built by the same fluent call the README shows at installation. If a
 * test in this suite passes, a host doing the same thing gets the same result.
 */
abstract class UsageTestCase extends TestCase {
	/**
	 * Booting a project registers its container with the facade, which is static and
	 * outlives the test. Both ends are cleared, so no test depends on its neighbours.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Twig::reset();
	}

	/**
	 * And nothing is left behind for the next one.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Twig::reset();

		parent::tearDown();
	}

	/**
	 * The root directory of a fixture project.
	 *
	 * @param string $name The project directory name.
	 * @return string
	 */
	protected function site( string $name = 'Site' ): string {
		return __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Fixtures'
			. DIRECTORY_SEPARATOR . 'Usage' . DIRECTORY_SEPARATOR . $name;
	}

	/**
	 * Boot a fixture project, the way the README tells a host to boot one.
	 *
	 * The environment is passed as a literal rather than guessed, because the helper
	 * that guesses it reaches for a function of the host platform that no test process
	 * has.
	 *
	 * @param string $name        The project directory name.
	 * @param string $environment The environment to boot in.
	 * @return Kernel
	 */
	protected function boot( string $name = 'Site', string $environment = Env::PRODUCTION ): Kernel {
		$root = $this->site( $name );

		return Kernel::configure( $root )
			->environment( $environment )
			->config( $root . DIRECTORY_SEPARATOR . 'config' )
			->boot();
	}

	/**
	 * The environment of a booted project.
	 *
	 * @param Kernel $kernel The booted kernel.
	 * @return Environment
	 */
	protected function twig( Kernel $kernel ): Environment {
		$twig = $kernel->service( 'twig' );

		$this->assertInstanceOf( Environment::class, $twig );

		return $twig;
	}
}
