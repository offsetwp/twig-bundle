<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * A full Twig extension, the kind a host writes when a filter needs a class of its own.
 */
final class ShopExtension extends AbstractExtension {
	/**
	 * {@inheritDoc}
	 *
	 * @return array<int, TwigFilter>
	 */
	public function getFilters(): array {
		return array( new TwigFilter( 'vat', array( $this, 'vat' ) ) );
	}

	/**
	 * Add VAT to an amount.
	 *
	 * @param float $amount The amount excluding tax.
	 * @param float $rate   The VAT rate.
	 * @return float
	 */
	public function vat( float $amount, float $rate = 0.2 ): float {
		return $amount * ( 1 + $rate );
	}
}
