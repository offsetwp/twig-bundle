<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Unit\Environment
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Unit\Environment;

use OffsetWP\Bundle\TwigBundle\Environment\CoreSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Loader\ArrayLoader;

/**
 * The configurator that applies the date and number settings.
 */
#[CoversClass( CoreSettings::class )]
final class CoreSettingsTest extends TestCase {
	/**
	 * The core extension of a bare environment, which is where the settings live.
	 *
	 * @param Environment $environment The environment.
	 * @return CoreExtension
	 */
	private function core( Environment $environment ): CoreExtension {
		return $environment->getExtension( CoreExtension::class );
	}

	/**
	 * The date settings, every one of them defaulting to Twig's own value.
	 *
	 * @param string      $format          The default date format.
	 * @param string      $interval_format The default interval format.
	 * @param string|null $timezone        The default timezone, or null for PHP's own.
	 * @return array{format: string, interval_format: string, timezone: string|null}
	 */
	private function date(
		string $format = CoreSettings::DEFAULT_DATE_FORMAT,
		string $interval_format = CoreSettings::DEFAULT_INTERVAL_FORMAT,
		?string $timezone = null
	): array {
		return array(
			'format'          => $format,
			'interval_format' => $interval_format,
			'timezone'        => $timezone,
		);
	}

	/**
	 * The number settings, every one of them defaulting to Twig's own value.
	 *
	 * @param int    $decimals            The number of decimals.
	 * @param string $decimal_point       The decimal separator.
	 * @param string $thousands_separator The thousands separator.
	 * @return array{decimals: int, decimal_point: string, thousands_separator: string}
	 */
	private function numberFormat(
		int $decimals = CoreSettings::DEFAULT_DECIMALS,
		string $decimal_point = CoreSettings::DEFAULT_DECIMAL_POINT,
		string $thousands_separator = CoreSettings::DEFAULT_THOUSANDS_SEPARATOR
	): array {
		return array(
			'decimals'            => $decimals,
			'decimal_point'       => $decimal_point,
			'thousands_separator' => $thousands_separator,
		);
	}

	/**
	 * The constants of this class are Twig's own values, so applying them to a fresh
	 * environment has to leave it exactly as it was. That is what makes it honest to
	 * register the configurator unconditionally.
	 *
	 * @return void
	 */
	public function testItAppliesNothingWhenNothingIsConfigured(): void {
		$environment = new Environment( new ArrayLoader() );
		$core        = $this->core( $environment );

		$date     = $core->getDateFormat();
		$number   = $core->getNumberFormat();
		$timezone = $core->getTimezone()->getName();

		( new CoreSettings( $this->date(), $this->numberFormat() ) )( $environment );

		$this->assertSame( $date, $core->getDateFormat() );
		$this->assertSame( $number, $core->getNumberFormat() );
		$this->assertSame( $timezone, $core->getTimezone()->getName() );
	}

	/**
	 * Both halves of the date setting, and the timezone, reach the core extension.
	 *
	 * @return void
	 */
	public function testItAppliesTheDateFormat(): void {
		$environment = new Environment( new ArrayLoader() );

		$settings = $this->date( 'd/m/Y H:i', '%d jours', 'Europe/Paris' );

		( new CoreSettings( $settings, $this->numberFormat() ) )( $environment );

		$core = $this->core( $environment );

		$this->assertSame( array( 'd/m/Y H:i', '%d jours' ), $core->getDateFormat() );
		$this->assertSame( 'Europe/Paris', $core->getTimezone()->getName() );
	}

	/**
	 * The three number settings reach the core extension in the order the filter
	 * expects them.
	 *
	 * @return void
	 */
	public function testItAppliesTheNumberFormat(): void {
		$environment = new Environment( new ArrayLoader() );

		$settings = $this->numberFormat( 2, ',', ' ' );

		( new CoreSettings( $this->date(), $settings ) )( $environment );

		$this->assertSame( array( 2, ',', ' ' ), $this->core( $environment )->getNumberFormat() );
	}
	/**
	 * The constants are copies of values the core extension keeps in private
	 * properties. Copies drift, and this one would drift silently: the tree's defaults
	 * and the service arguments both come from here, so a Twig release changing a
	 * default would leave every project on the old one with nothing said anywhere.
	 *
	 * Asking the extension itself is the only assertion that can catch it. The case
	 * that applies the constants and expects no change cannot: it compares Twig to an
	 * environment built from those same constants, which is Twig against itself.
	 *
	 * @return void
	 */
	public function testTheConstantsStillSayWhatTwigSays(): void {
		$core = new CoreExtension();

		$this->assertSame(
			array( CoreSettings::DEFAULT_DATE_FORMAT, CoreSettings::DEFAULT_INTERVAL_FORMAT ),
			$core->getDateFormat()
		);

		$this->assertSame(
			array( CoreSettings::DEFAULT_DECIMALS, CoreSettings::DEFAULT_DECIMAL_POINT, CoreSettings::DEFAULT_THOUSANDS_SEPARATOR ),
			$core->getNumberFormat()
		);
	}
}
