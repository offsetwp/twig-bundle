<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Fixtures;

/**
 * An escaping strategy a host could write, expressed as an array callable.
 *
 * A closure would read better and cannot be used: see the autoescape section of the
 * README. An array callable is a real PHP callable, it survives compilation, and it
 * is what a host writes when the strategy needs more than a name.
 */
final class EscapingStrategy {
	/**
	 * Pick an escaping strategy from the template name.
	 *
	 * @param string $name The template name.
	 * @return string|false The strategy, or false to escape nothing.
	 */
	public static function guess( string $name ): string|false {
		return str_ends_with( $name, '.txt.twig' ) ? false : 'html';
	}
}
