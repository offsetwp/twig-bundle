<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Usage
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Usage;

use OffsetWP\Bundle\TwigBundle\TwigBundle;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * A project configured by the file a host writes: config/packages/twig.php.
 *
 * Every configuration case outside this suite hands its configuration to a test kernel
 * as an array. This one writes the file instead, and the kernel finds it by the same
 * glob it uses on a real site.
 */
#[CoversClass( TwigBundle::class )]
final class PhpConfigurationTest extends UsageTestCase {
	/**
	 * The default directory nobody configured, found because it is called templates/.
	 *
	 * @return void
	 */
	public function testTheDefaultDirectoryIsSearchedWithoutBeingNamed(): void {
		$this->assertSame(
			"Hello, Jérôme!\n",
			$this->twig( $this->boot() )->render( 'hello.twig', array( 'name' => 'Jérôme' ) )
		);
	}

	/**
	 * A directory named in the file, under a namespace of its own, alongside the
	 * default one rather than instead of it.
	 *
	 * The project names it with %kernel.root_path%, which is what the README shows and
	 * what spares a configuration file from counting its own depth in "../". The
	 * parameter sits in a mapping key rather than in a value, which is the half of that
	 * resolution worth asserting.
	 *
	 * @return void
	 */
	public function testAConfiguredNamespacedDirectoryIsSearchedToo(): void {
		$this->assertSame(
			"Welcome, Jérôme.\n",
			$this->twig( $this->boot() )->render( '@mail/welcome.twig', array( 'name' => 'Jérôme' ) )
		);
	}

	/**
	 * The globals, the date format, the timezone and the number format, all four
	 * written in that file and all four reaching Twig.
	 *
	 * @return void
	 */
	public function testEveryConfiguredSettingReachesTheEnvironment(): void {
		$rendered = $this->twig( $this->boot() )->render(
			'configured.twig',
			array( 'opened' => new \DateTimeImmutable( '2026-01-01 12:00:00', new \DateTimeZone( 'UTC' ) ) )
		);

		$this->assertSame( "Éditions Exemple|01/01/2026|1 234,50\n", $rendered );
	}
}
