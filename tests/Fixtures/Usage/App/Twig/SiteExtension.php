<?php
/**
 * A fixture project of the OffsetWP Twig Bundle test suite.
 *
 * @author Jérôme Wohlschlegel
 * @package App\Twig
 */

declare( strict_types=1 );

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * A full extension, the kind a project writes when a filter wants a class of its own.
 *
 * One of its filters points at itself and the other at a runtime class, which are the
 * two ways of declaring one and the reason the runtime loader has to be wired.
 */
final class SiteExtension extends AbstractExtension {
	/**
	 * {@inheritDoc}
	 *
	 * @return array<int, TwigFilter>
	 */
	public function getFilters(): array {
		return array(
			new TwigFilter( 'slug', array( $this, 'slug' ) ),
			new TwigFilter( 'fee', array( MembershipRuntime::class, 'fee' ) ),
		);
	}

	/**
	 * A title as it goes into a URL.
	 *
	 * @param string $title The title.
	 * @return string
	 */
	public function slug( string $title ): string {
		return trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $title ) ), '-' );
	}
}
