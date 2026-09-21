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
use OffsetWP\Bundle\TwigBundle\TwigBundle;
use OffsetWP\Framework\Kernel;
use OffsetWP\Support\Env;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The three ways a project builds its kernel, one of which works.
 *
 * The README opens on the fluent call and then spends a paragraph on what happens
 * when it is written the other way: the kernel registers no bundles at all, none of
 * this package's code runs, and there is no hook from which that could be reported.
 * That paragraph has never been checked against the code, and neither has the call it
 * warns about.
 */
#[CoversClass( TwigBundle::class )]
#[CoversClass( Twig::class )]
final class KernelBuildingTest extends UsageTestCase {
	/**
	 * The call the README prints at installation, with the debug flag the kernel
	 * publishes and this bundle follows.
	 *
	 * @return void
	 */
	public function testTheFluentCallTheReadmePrintsBuildsAWorkingProject(): void {
		$root = $this->site();

		$kernel = Kernel::configure( $root )
			->environment( Env::PRODUCTION )
			->debug( true )
			->config( $root . '/config' )
			->boot();

		$twig = $this->twig( $kernel );

		$this->assertTrue( $twig->isDebug() );
		$this->assertSame( "Hello, Jérôme!\n", $twig->render( 'hello.twig', array( 'name' => 'Jérôme' ) ) );
	}

	/**
	 * Forgetting the config() call. The kernel has no config directory to read, so it
	 * finds no bundle list, registers nothing, and boots into a project where this
	 * package does not exist.
	 *
	 * @return void
	 */
	public function testWithoutAConfigDirectoryTheBundleIsNeverRegistered(): void {
		$kernel = Kernel::configure( $this->site() )
			->environment( Env::PRODUCTION )
			->boot();

		$this->assertFalse( $kernel->hasService( 'twig' ) );
		$this->assertFalse( Twig::booted() );
	}

	/**
	 * Handing the kernel a services file instead of a config directory. Same outcome,
	 * and it is the one the README names in Troubleshooting.
	 *
	 * @return void
	 */
	public function testWithAServicesFileTheBundleIsNeverRegisteredEither(): void {
		$root = $this->site();

		$kernel = Kernel::configure( $root )
			->environment( Env::PRODUCTION )
			->services( $root . '/config/services.php' )
			->boot();

		$this->assertFalse( $kernel->hasService( 'twig' ) );
		$this->assertFalse( Twig::booted() );
	}

	/**
	 * What a project in that state is told when it calls the helper.
	 *
	 * "No kernel carrying the Twig bundle has booted" is true, and on its own it sends
	 * the reader to boot a kernel they have already booted. The sentence that ends the
	 * search is the next one, because it is the only place the cause appears at all:
	 * from inside a bundle whose code never ran, nothing can be detected.
	 *
	 * @return void
	 */
	public function testTheFailureNamesTheCauseAProjectCannotSee(): void {
		Kernel::configure( $this->site() )
			->environment( Env::PRODUCTION )
			->boot();

		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage(
			'the kernel was built in configuration mode, because one handed a services file instead of a config directory registers no bundles at all'
		);

		twig( 'hello.twig' );
	}
}
