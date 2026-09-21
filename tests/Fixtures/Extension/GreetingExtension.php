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
use Twig\TwigFunction;

/**
 * A second full extension, so that ordering has something to order.
 */
final class GreetingExtension extends AbstractExtension {
	/**
	 * {@inheritDoc}
	 *
	 * @return array<int, TwigFunction>
	 */
	public function getFunctions(): array {
		return array( new TwigFunction( 'greet', array( $this, 'greet' ) ) );
	}

	/**
	 * Greet somebody.
	 *
	 * @param string $name The name to greet.
	 * @return string
	 */
	public function greet( string $name ): string {
		return 'Hello, ' . $name . '!';
	}
}
