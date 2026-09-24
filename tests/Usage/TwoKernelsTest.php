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
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Two whole projects in one process, each with its own config directory.
 *
 * On this platform a mu-plugin and a theme each boot a kernel, so this is the ordinary
 * arrangement rather than an edge case — and both projects here call their template
 * hello.twig, which is what the arrangement looks like in practice.
 */
#[CoversClass( TwigBundle::class )]
#[CoversClass( Twig::class )]
final class TwoKernelsTest extends UsageTestCase {
	/**
	 * Boot both, in the order the platform boots them.
	 *
	 * @return array{Kernel, Kernel}
	 */
	private function bootBoth(): array {
		return array( $this->boot(), $this->boot( 'Theme' ) );
	}

	/**
	 * Each project owns its own environment, its own configuration and its own
	 * templates, and the two share nothing — not even a compiled template, although
	 * both call theirs hello.twig.
	 *
	 * @return void
	 */
	public function testEachProjectKeepsItsOwnEnvironment(): void {
		list( $site, $theme ) = $this->bootBoth();

		$context = array( 'name' => 'Jérôme' );

		$this->assertNotSame( $site->container(), $theme->container() );
		$this->assertNotSame( $this->twig( $site ), $this->twig( $theme ) );
		$this->assertSame( "Hello, Jérôme!\n", $this->twig( $site )->render( 'hello.twig', $context ) );
		$this->assertSame( "Bonjour, Jérôme !\n", $this->twig( $theme )->render( 'hello.twig', $context ) );
	}

	/**
	 * And its own configuration: the global each project wrote in its own file is the
	 * one its own templates see.
	 *
	 * @return void
	 */
	public function testEachProjectKeepsItsOwnConfiguration(): void {
		list( $site, $theme ) = $this->bootBoth();

		$this->assertSame( "Éditions Exemple\n", $this->twig( $site )->render( 'owner.twig' ) );
		$this->assertSame( "Le thème\n", $this->twig( $theme )->render( 'owner.twig' ) );
	}

	/**
	 * The shortcut designates the last project to boot, which is the theme — the one
	 * a developer is editing when they type twig() in a template file.
	 *
	 * @return void
	 */
	public function testTheShortcutDesignatesTheLastProjectBooted(): void {
		list( $site, $theme ) = $this->bootBoth();

		$this->assertSame( $this->twig( $theme ), twig() );
		$this->assertSame( "Bonjour, Jérôme !\n", twig( 'hello.twig', array( 'name' => 'Jérôme' ) ) );
		$this->assertSame( $this->twig( $site ), Twig::of( $site ) );
	}
}
