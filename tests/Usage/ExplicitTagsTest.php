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
 * The same classes as the project next door, wired the other way.
 *
 * Autoconfiguration is off and every point of entry is named by a tag written by hand.
 * The README says every one of them still works this way; this is what makes that a
 * promise rather than a hope, and it is also the only place the loader priority a host
 * writes is exercised against the filesystem loader on a template both of them have.
 */
#[CoversClass( TwigBundle::class )]
final class ExplicitTagsTest extends UsageTestCase {
	/**
	 * A full extension, a class of attributes and a runtime, all three named by a tag.
	 *
	 * @return void
	 */
	public function testExtensionsAttributesAndRuntimesWorkThroughTags(): void {
		$this->assertSame(
			"spring-concert-2026|120.00|60.00|25|yes\n",
			$this->twig( $this->boot( 'SiteWithTags' ) )->render( 'site.twig' )
		);
	}

	/**
	 * And so does a tag of the project's own.
	 *
	 * @return void
	 */
	public function testATokenParserWorksThroughATag(): void {
		$this->assertSame( 'Bonjour, Jérôme', $this->twig( $this->boot( 'SiteWithTags' ) )->render( 'tag.twig' ) );
	}

	/**
	 * A loader given a priority above the filesystem one answers first for a template
	 * they both have. This is the documented reason the tag carries a priority at all.
	 *
	 * @return void
	 */
	public function testAPriorityPutsAHostLoaderAheadOfTheFilesystem(): void {
		$this->assertSame( 'from the loader', $this->twig( $this->boot( 'SiteWithTags' ) )->render( 'notice.twig' ) );
	}

	/**
	 * And the filesystem is still there behind it.
	 *
	 * @return void
	 */
	public function testTheFilesystemLoaderRemainsTheFallback(): void {
		$this->assertSame(
			"Hello, Jérôme!\n",
			$this->twig( $this->boot( 'SiteWithTags' ) )->render( 'hello.twig', array( 'name' => 'Jérôme' ) )
		);
	}
}
