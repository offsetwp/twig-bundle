<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension;

/**
 * An ordinary service, the kind an attributed class depends on. Its only job here is
 * to be something the container has to construct before the filter can run.
 */
final class CurrencyRates {
	/**
	 * Format an amount in cents.
	 *
	 * @param int    $cents    The amount, in cents.
	 * @param string $currency The ISO currency code.
	 * @return string
	 */
	public function format( int $cents, string $currency ): string {
		return number_format( $cents / 100, 2, ',', ' ' ) . ' ' . $currency;
	}

	/**
	 * The exchange rate of a currency.
	 *
	 * @param string $currency The ISO currency code.
	 * @return float
	 */
	public function rate( string $currency ): float {
		return 'USD' === $currency ? 1.1 : 1.0;
	}
}
