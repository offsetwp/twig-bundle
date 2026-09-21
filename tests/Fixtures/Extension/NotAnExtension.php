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
 * A plain class, so that the checks refusing one have something to refuse.
 */
final class NotAnExtension {
	/**
	 * Something to call, so that the class is not empty.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'not an extension';
	}
}
