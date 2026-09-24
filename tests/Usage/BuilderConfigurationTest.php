<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Usage
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Usage;

use OffsetWP\Bundle\TwigBundle\Configuration\TwigConfig;
use OffsetWP\Bundle\TwigBundle\TwigBundle;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * A whole project configured by the builder rather than by an array.
 *
 * The unit suite asserts that the two forms process into one configuration. This says
 * the rest of it: that the file a project writes this way is found by the same glob,
 * read by the same loader and acted on by the same extension — that the fluent form is
 * a way of writing config/packages/twig.php and not a parallel mechanism.
 */
#[CoversClass( TwigBundle::class )]
#[CoversClass( TwigConfig::class )]
final class BuilderConfigurationTest extends UsageTestCase {
	/**
	 * The default directory is still found, with the configuration written this way.
	 *
	 * @return void
	 */
	public function testTheDefaultDirectoryIsStillSearched(): void {
		$this->assertSame(
			"Hello, Jérôme!\n",
			$this->twig( $this->boot( 'SiteFromBuilder' ) )->render( 'hello.twig', array( 'name' => 'Jérôme' ) )
		);
	}

	/**
	 * And a directory named by one path() call, under its namespace.
	 *
	 * @return void
	 */
	public function testAPathCallNamesADirectoryUnderItsNamespace(): void {
		$this->assertSame(
			"Welcome, Jérôme.\n",
			$this->twig( $this->boot( 'SiteFromBuilder' ) )->render( '@mail/welcome.twig', array( 'name' => 'Jérôme' ) )
		);
	}

	/**
	 * Everything else in one render: a plain global, a global naming a service, a
	 * global holding a literal that starts with an "@", the date format, the timezone
	 * and the three number settings.
	 *
	 * The literal is the one worth watching. Written by hand it is "@@offsetwp", a
	 * doubling a reader can only learn from the documentation; written here it is
	 * globalLiteral(), and what reaches the template is the string as it was typed.
	 *
	 * @return void
	 */
	public function testEveryMethodCallReachesTheEnvironment(): void {
		$rendered = $this->twig( $this->boot( 'SiteFromBuilder' ) )->render(
			'built.twig',
			array( 'opened' => new \DateTimeImmutable( '2026-01-01 12:00:00', new \DateTimeZone( 'UTC' ) ) )
		);

		$this->assertSame( "Éditions Exemple|@offsetwp|0.2|01/01/2026|1 234,50\n", $rendered );
	}
}
