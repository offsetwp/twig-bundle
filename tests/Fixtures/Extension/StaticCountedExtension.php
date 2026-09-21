<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension;

use Twig\Attribute\AsTwigTest;

/**
 * The same counter, on a class whose only attributed method is static.
 *
 * A static method compiles to a direct call, so this class is never constructed at
 * all — not on boot, not on the first use, not ever.
 */
final class StaticCountedExtension {

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
	 * Whether a value is zero.
	 *
	 * @param int $value The value.
	 * @return bool
	 */
	#[AsTwigTest( name: 'counted_static' )]
	public static function isZero( int $value ): bool {
		return 0 === $value;
	}
}
