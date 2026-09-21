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
 * The mistake this bundle refuses to compile.
 *
 * The public method is what gets the class tagged at all; the one below it carries
 * the same attribute and cannot be called. Twig's own reader scans every method
 * whatever its visibility, so without a check the filter would exist and fail at the
 * first call.
 */
final class HiddenFilterExtension {
	/**
	 * A filter that works.
	 *
	 * @param string $text The text.
	 * @return string
	 */
	#[AsTwigFilter( name: 'visible' )]
	public function visible( string $text ): string {
		return $text;
	}

	/**
	 * A filter that cannot work.
	 *
	 * @param string $text The text.
	 * @return string
	 */
	#[AsTwigFilter( name: 'hidden' )]
	protected function hidden( string $text ): string {
		return $text;
	}
}
