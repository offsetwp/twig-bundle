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
 * The same bundle, configured in YAML, by a project that writes no services at all.
 *
 * The kernel globs config/packages for *.yaml as readily as for *.php, and until this
 * suite no YAML file had ever been loaded here — the component that reads one was not
 * installed, so the glob simply never matched.
 *
 * It is a different exercise and not a restatement of the PHP one. A YAML file has no
 * __DIR__, so a directory has to be named through a kernel parameter in a mapping key;
 * a number arrives as an int and a quoted separator as a string; and this project owns
 * no classes, so its one extension comes from the "extensions" key rather than from a
 * services file.
 */
#[CoversClass( TwigBundle::class )]
final class YamlConfigurationTest extends UsageTestCase {
	/**
	 * A project whose whole configuration is one YAML file renders from its default
	 * directory, with no services file anywhere.
	 *
	 * @return void
	 */
	public function testAProjectConfiguredOnlyInYamlRenders(): void {
		$this->assertSame(
			"Hello, Jérôme!\n",
			$this->twig( $this->boot( 'SiteInYaml' ) )->render( 'hello.twig', array( 'name' => 'Jérôme' ) )
		);
	}

	/**
	 * A directory named by a kernel parameter inside a mapping key, which is the only
	 * way a YAML file can name one.
	 *
	 * @return void
	 */
	public function testADirectoryIsNamedThroughAKernelParameter(): void {
		$this->assertSame(
			"Welcome, Jérôme.\n",
			$this->twig( $this->boot( 'SiteInYaml' ) )->render( '@mail/welcome.twig', array( 'name' => 'Jérôme' ) )
		);
	}

	/**
	 * The extension installed by the one-line key, and the three settings written
	 * alongside it, all arriving from YAML.
	 *
	 * @return void
	 */
	public function testTheExtensionsKeyAndEverySettingArriveFromYaml(): void {
		$rendered = $this->twig( $this->boot( 'SiteInYaml' ) )->render(
			'yaml.twig',
			array( 'opened' => new \DateTimeImmutable( '2026-01-01 12:00:00', new \DateTimeZone( 'UTC' ) ) )
		);

		$this->assertSame( "hello-world-2026|Éditions Exemple|01/01/2026|1 234,50\n", $rendered );
	}
}
