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

/**
 * An attributed class declaring the same filter name as ShopExtension, and returning
 * the amount untouched, so that a render says which of the two reached Twig last.
 *
 * The method is static because a static one is compiled to a direct call: nothing
 * about this fixture has to involve the runtime loader.
 */
final class AttributedVatExtension {

	/**
	 * The amount, with no tax added.
	 *
	 * @param float $amount The amount before tax.
	 * @return float
	 */
	#[AsTwigFilter( name: 'vat' )]
	public static function vat( float $amount ): float {
		return $amount;
	}
}
