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
use OffsetWP\Support\Env;
use PHPUnit\Framework\Attributes\CoversClass;
use Twig\Error\LoaderError;

/**
 * A project whose configuration comes from two files, and a bundle list that turns
 * this bundle on in one environment only.
 *
 * The kernel imports config/packages first and config/packages/<environment> after,
 * so the tree receives two configurations and merges them. Every other test in this
 * repository hands it exactly one, which means the merge — what a second file does to
 * a map, to a list, to a scalar — has never been looked at.
 */
#[CoversClass( TwigBundle::class )]
final class EnvironmentConfigurationTest extends UsageTestCase {
	/**
	 * The moment rendered by the cases that print a date, pinned so that no assertion
	 * depends on the clock.
	 *
	 * @return array<string, \DateTimeImmutable>
	 */
	private function moment(): array {
		return array( 'opened' => new \DateTimeImmutable( '2026-01-01 12:00:00', new \DateTimeZone( 'UTC' ) ) );
	}

	/**
	 * Outside production the second file is not imported at all, so what the project
	 * gets is the base configuration and nothing else.
	 *
	 * @return void
	 */
	public function testAnEnvironmentWithNoFileOfItsOwnGetsTheBaseConfiguration(): void {
		$twig = $this->twig( $this->boot( 'SiteInStages', Env::LOCAL ) );

		$this->assertSame( "base|from base|2026-01-01\n", $twig->render( 'stage.twig', $this->moment() ) );
		$this->assertSame( "only base\n", $twig->render( '@base/only-base.twig' ) );
	}

	/**
	 * And in that environment the directory the production file names is not searched,
	 * because that file was never read.
	 *
	 * @return void
	 */
	public function testTheOtherEnvironmentsDirectoryIsNotSearched(): void {
		$this->expectException( LoaderError::class );

		$this->twig( $this->boot( 'SiteInStages', Env::LOCAL ) )->render( '@prod/only-prod.twig' );
	}

	/**
	 * In production both files are read. A scalar written twice keeps the value of the
	 * file read last, which is the environment's.
	 *
	 * @return void
	 */
	public function testTheEnvironmentFileWinsOnAScalar(): void {
		$this->assertStringEndsWith(
			"|01/01/2026\n",
			$this->twig( $this->boot( 'SiteInStages', Env::PRODUCTION ) )->render( 'stage.twig', $this->moment() )
		);
	}

	/**
	 * A map written in both files is merged key by key rather than replaced: the entry
	 * the environment overrides takes the new value, and the entry it says nothing
	 * about survives. This is what decides whether a project can add one global in
	 * production without repeating the other four.
	 *
	 * @return void
	 */
	public function testAMapIsMergedKeyByKeyAndNotReplaced(): void {
		$this->assertSame(
			"production|from base|01/01/2026\n",
			$this->twig( $this->boot( 'SiteInStages', Env::PRODUCTION ) )->render( 'stage.twig', $this->moment() )
		);
	}

	/**
	 * And the directories accumulate, so production searches its own and the base one
	 * both.
	 *
	 * @return void
	 */
	public function testTemplateDirectoriesAccumulateAcrossTheTwoFiles(): void {
		$twig = $this->twig( $this->boot( 'SiteInStages', Env::PRODUCTION ) );

		$this->assertSame( "only prod\n", $twig->render( '@prod/only-prod.twig' ) );
		$this->assertSame( "only base\n", $twig->render( '@base/only-base.twig' ) );
	}

	/**
	 * A bundle list can turn a bundle on in one environment and not another. Booted
	 * where it is off, the kernel registers nothing of this package — and what a
	 * project sees then is worth pinning, because it is indistinguishable from having
	 * forgotten to install it.
	 *
	 * @return void
	 */
	public function testABundleTurnedOnInOneEnvironmentOnlyIsAbsentFromTheOthers(): void {
		$this->assertTrue( $this->boot( 'SiteForProductionOnly', Env::PRODUCTION )->hasService( 'twig' ) );
		$this->assertFalse( $this->boot( 'SiteForProductionOnly', Env::LOCAL )->hasService( 'twig' ) );
	}
}
