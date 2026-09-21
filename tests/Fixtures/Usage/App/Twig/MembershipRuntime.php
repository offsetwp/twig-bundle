<?php
/**
 * A fixture project of the OffsetWP Twig Bundle test suite.
 *
 * @author Jérôme Wohlschlegel
 * @package App\Twig
 */

declare( strict_types=1 );

namespace App\Twig;

use Twig\Extension\RuntimeExtensionInterface;

/**
 * What the "fee" filter of SiteExtension is actually implemented by.
 *
 * Declaring a filter against a class name rather than against $this is how an
 * extension stays cheap: the extension is built when Twig starts, the runtime only
 * when a template calls the filter.
 */
final class MembershipRuntime implements RuntimeExtensionInterface {
	/**
	 * Constructor.
	 *
	 * @param Rates $rates The project's own rates service.
	 * @return void
	 */
	public function __construct( private Rates $rates ) {
	}

	/**
	 * A membership fee for a number of years, taxed.
	 *
	 * @param int $years How many years.
	 * @return string
	 */
	public function fee( int $years ): string {
		return sprintf( '%.2f', PriceExtension::membershipFee() * $years * ( 1 + $this->rates->forYear( 2026 ) ) );
	}
}
