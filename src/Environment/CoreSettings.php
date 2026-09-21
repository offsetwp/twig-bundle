<?php
/**
 * OffsetWP Twig Bundle
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Environment
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Environment;

use Twig\Environment;
use Twig\Extension\CoreExtension;

/**
 * Applies the date and number settings to a freshly built environment.
 *
 * This class exists for exactly one reason and does nothing else. The date and number
 * setters live on the environment's own core extension, which no method call on the
 * environment definition can reach without the container building the environment to
 * get at the extension and the extension to get at the environment. A configurator
 * runs after the environment exists and sidesteps that entirely.
 *
 * The constants are copies of Twig's own defaults, and they are the single source of
 * both the service arguments and the configuration tree's defaults. Copies, not
 * readings: the core extension keeps them in private properties with no constant to
 * point at, so the only way to hold them at compile time is to write them down.
 * CoreSettingsTest asserts they still say what the extension says, which is what makes
 * a Twig release changing one of them a red build rather than a silent change of
 * behaviour in every project.
 *
 * Note for anyone editing this file: the namespace ends in Environment, so the
 * unqualified name Environment would resolve inside it were it not imported. Every
 * Twig class is imported here, always.
 */
final class CoreSettings {

	/**
	 * The format the date filter uses when it is given none.
	 *
	 * @var string
	 */
	public const DEFAULT_DATE_FORMAT = 'F j, Y H:i';

	/**
	 * The format the date filter uses for an interval.
	 *
	 * @var string
	 */
	public const DEFAULT_INTERVAL_FORMAT = '%d days';

	/**
	 * The number of decimals the number_format filter uses when it is given none.
	 *
	 * @var int
	 */
	public const DEFAULT_DECIMALS = 0;

	/**
	 * The decimal separator the number_format filter uses.
	 *
	 * @var string
	 */
	public const DEFAULT_DECIMAL_POINT = '.';

	/**
	 * The thousands separator the number_format filter uses.
	 *
	 * @var string
	 */
	public const DEFAULT_THOUSANDS_SEPARATOR = ',';

	/**
	 * Constructor.
	 *
	 * @param array{format: string, interval_format: string, timezone: string|null}    $date          The date settings.
	 * @param array{decimals: int, decimal_point: string, thousands_separator: string} $number_format The number settings.
	 * @return void
	 */
	public function __construct( private array $date, private array $number_format ) {
	}

	/**
	 * Apply the settings to an environment.
	 *
	 * @param Environment $environment The environment being built.
	 * @return void
	 */
	public function __invoke( Environment $environment ): void {
		$core = $environment->getExtension( CoreExtension::class );

		$core->setDateFormat( $this->date['format'], $this->date['interval_format'] );

		if ( null !== $this->date['timezone'] ) {
			$core->setTimezone( $this->date['timezone'] );
		}

		$core->setNumberFormat(
			$this->number_format['decimals'],
			$this->number_format['decimal_point'],
			$this->number_format['thousands_separator']
		);
	}
}
