<?php
/**
 * A fixture project of the OffsetWP Twig Bundle test suite.
 *
 * @author Jérôme Wohlschlegel
 * @package App\Twig
 */

declare( strict_types=1 );

namespace App\Twig;

/**
 * An ordinary service of the project, with nothing to do with Twig.
 *
 * It is here to be a constructor dependency of something Twig reaches, which is what
 * makes the autowiring of an extension worth proving rather than assuming.
 */
final class Rates {
	/**
	 * The tax rate of a year.
	 *
	 * @param int $year The year.
	 * @return float
	 */
	public function forYear( int $year ): float {
		return 2026 === $year ? 0.2 : 0.1;
	}
}
