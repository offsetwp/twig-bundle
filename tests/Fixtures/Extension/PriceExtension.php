<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension;

use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Attribute\AsTwigTest;

/**
 * One class, three attributes, no registration anywhere.
 *
 * The two non-static methods are what makes this class a runtime: Twig compiles them
 * to a call on the runtime loader, so neither this class nor its dependency is
 * constructed until a rendered template actually reaches one of them. The static one
 * compiles to a direct call and constructs nothing, ever.
 */
final class PriceExtension {
	/**
	 * Constructor.
	 *
	 * @param CurrencyRates $rates The live rates provider.
	 * @return void
	 */
	public function __construct( private CurrencyRates $rates ) {
	}

	/**
	 * Format an amount in cents as a localised price.
	 *
	 * @param int    $cents    The amount, in cents.
	 * @param string $currency The ISO currency code.
	 * @return string
	 */
	#[AsTwigFilter( name: 'price' )]
	public function price( int $cents, string $currency = 'EUR' ): string {
		return $this->rates->format( $cents, $currency );
	}

	/**
	 * Return the current exchange rate.
	 *
	 * @param string $currency The ISO currency code.
	 * @return float
	 */
	#[AsTwigFunction( name: 'rate' )]
	public function rate( string $currency ): float {
		return $this->rates->rate( $currency );
	}

	/**
	 * Whether an amount is free.
	 *
	 * @param int $cents The amount, in cents.
	 * @return bool
	 */
	#[AsTwigTest( name: 'free' )]
	public static function isFree( int $cents ): bool {
		return 0 === $cents;
	}
}
