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
 * An attributed class that counts how many times it was constructed.
 *
 * The whole laziness guarantee of this package is one number: this counter has to
 * stay at zero until a rendered template actually reaches the filter below, and it
 * has to stop at one however many times it is rendered after that.
 */
final class CountedExtension {

	/**
	 * How many times this class has been constructed.
	 *
	 * @var int
	 */
	public static int $constructions = 0;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		++self::$constructions;
	}

	/**
	 * A filter that does nothing, so that only the construction is observed.
	 *
	 * @param string $text The text.
	 * @return string
	 */
	#[AsTwigFilter( name: 'counted' )]
	public function counted( string $text ): string {
		return $text;
	}
}
