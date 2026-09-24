<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Bundle
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Bundle;

/**
 * A bag of HTML attributes that prints itself, escaping every value on the way out —
 * the class that bundle marks safe, since printing it escaped again ruins it.
 */
final class Attributes implements \Stringable {

	/**
	 * Constructor.
	 *
	 * @param array<string, string> $attributes The attributes, name to value.
	 * @return void
	 */
	public function __construct( private array $attributes ) {
	}

	/**
	 * The attributes as they go inside a tag, each with a space before it.
	 *
	 * @return string
	 */
	public function __toString(): string {
		$html = '';

		foreach ( $this->attributes as $name => $value ) {
			$html .= sprintf( ' %s="%s"', $name, htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ) );
		}

		return $html;
	}
}
