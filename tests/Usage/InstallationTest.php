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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use Twig\Environment;

/**
 * The installation the README describes, and nothing else.
 *
 * The project is a config/bundles.php naming this bundle and a templates/ directory.
 * No config/packages, no config/services.php, no class of its own. That is the whole
 * of it, and the kernel's own fallback — loading an extension with an empty
 * configuration when the project wrote none — is what makes it work. Nothing had ever
 * taken that fallback through the real path.
 */
#[CoversClass( TwigBundle::class )]
#[CoversClass( Twig::class )]
#[CoversFunction( 'twig' )]
final class InstallationTest extends UsageTestCase {
	/**
	 * Two lines in a file and a directory, and a template renders.
	 *
	 * @return void
	 */
	public function testAProjectThatConfiguresNothingRenders(): void {
		$this->assertSame(
			"Hello, Jérôme!\n",
			$this->twig( $this->boot( 'SiteWithoutConfiguration' ) )->render( 'hello.twig', array( 'name' => 'Jérôme' ) )
		);
	}

	/**
	 * Every way in hands back the same object. They are five different spellings of
	 * one service, and a host picks whichever suits the file they are in — so the
	 * thing worth asserting is that none of them is a different environment.
	 *
	 * @return void
	 */
	public function testEveryWayInIsTheSameEnvironment(): void {
		$kernel = $this->boot( 'SiteWithoutConfiguration' );
		$twig   = $this->twig( $kernel );

		$this->assertSame( $twig, twig() );
		$this->assertSame( $twig, Twig::environment() );
		$this->assertSame( $twig, Twig::of( $kernel ) );
		$this->assertSame( $twig, Twig::of( $kernel->container() ) );
		$this->assertSame( $twig, $kernel->service( Environment::class ) );
	}

	/**
	 * And every way of asking for a rendered template gives the same string.
	 *
	 * @return void
	 */
	public function testEveryWayOfRenderingGivesTheSameString(): void {
		$this->boot( 'SiteWithoutConfiguration' );

		$context  = array( 'name' => 'Jérôme' );
		$expected = "Hello, Jérôme!\n";

		$this->assertSame( $expected, twig( 'hello.twig', $context ) );
		$this->assertSame( $expected, Twig::render( 'hello.twig', $context ) );

		$this->expectOutputString( $expected );

		Twig::display( 'hello.twig', $context );
	}

	/**
	 * The question a theme file asks before it renders anything, answered yes once the
	 * project has booted and no before.
	 *
	 * @return void
	 */
	public function testBootedAnswersForAProjectThatInstalledTheBundle(): void {
		$this->assertFalse( Twig::booted() );

		$this->boot( 'SiteWithoutConfiguration' );

		$this->assertTrue( Twig::booted() );
	}
}
