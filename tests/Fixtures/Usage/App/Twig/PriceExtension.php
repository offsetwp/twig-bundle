<?php
/**
 * A fixture project of the OffsetWP Twig Bundle test suite.
 *
 * @author Jérôme Wohlschlegel
 * @package App\Twig
 */

declare( strict_types=1 );

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Attribute\AsTwigTest;

/**
 * The first thing a project writes: one class, three attributes, no registration.
 *
 * The filter is not static and takes a dependency, so Twig reaches it through the
 * runtime loader and the container builds it — once, and only if a template calls it.
 * The function and the test are static, so they compile to a direct call and the class
 * is never constructed for them. Both shapes in one class is the ordinary case.
 */
final class PriceExtension {
	/**
	 * Constructor.
	 *
	 * @param Rates $rates The project's own rates service.
	 * @return void
	 */
	public function __construct( private Rates $rates ) {
	}

	/**
	 * An amount with this year's tax added, as it is printed.
	 *
	 * @param float $amount The amount before tax.
	 * @return string
	 */
	#[AsTwigFilter( name: 'price' )]
	public function price( float $amount ): string {
		return sprintf( '%.2f', $amount * ( 1 + $this->rates->forYear( 2026 ) ) );
	}

	/**
	 * What a membership costs.
	 *
	 * @return int
	 */
	#[AsTwigFunction( name: 'membership_fee' )]
	public static function membershipFee(): int {
		return 25;
	}

	/**
	 * Whether an amount costs nothing.
	 *
	 * @param float $amount The amount.
	 * @return bool
	 */
	#[AsTwigTest( name: 'free' )]
	public static function isFree( float $amount ): bool {
		return 0.0 === $amount;
	}
}
