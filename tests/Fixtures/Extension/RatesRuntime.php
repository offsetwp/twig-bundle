<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension;

use Twig\Extension\RuntimeExtensionInterface;

/**
 * A runtime that says so, the way most of them do.
 */
final class RatesRuntime implements RuntimeExtensionInterface {
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
