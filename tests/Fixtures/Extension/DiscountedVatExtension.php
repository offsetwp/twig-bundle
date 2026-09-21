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
 * A second extension declaring a filter the first one already declares.
 *
 * Twig lets the last registration of a name win, and overriding a filter is a
 * legitimate thing for a project to want — so which of the two is registered last is
 * a decision, and the tag priority is how it is made.
 */
final class DiscountedVatExtension extends AbstractExtension {
	/**
	 * {@inheritDoc}
	 *
	 * @return array<int, TwigFilter>
	 */
	public function getFilters(): array {
		return array( new TwigFilter( 'vat', array( $this, 'vat' ) ) );
	}

	/**
	 * Add no VAT at all.
	 *
	 * @param float $amount The amount excluding tax.
	 * @return float
	 */
	public function vat( float $amount ): float {
		return $amount;
	}
}
