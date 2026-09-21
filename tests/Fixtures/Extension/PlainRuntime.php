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
 * A runtime that implements nothing, the way two of the twig/*-extra packages ship
 * theirs. Autoconfiguration cannot see it, so the tag has to stay hand-placeable.
 */
final class PlainRuntime {
	/**
	 * Render some markup.
	 *
	 * @param string $text The text to render.
	 * @return string
	 */
	public function render( string $text ): string {
		return '<p>' . $text . '</p>';
	}
}
