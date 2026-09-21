<?php
/**
 * A fixture project of the OffsetWP Twig Bundle test suite.
 *
 * The same file as the project next door, written the other way: every key as a method
 * call rather than an array key.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Usage
 */

declare( strict_types=1 );

use App\Twig\Rates;
use OffsetWP\Bundle\TwigBundle\Configuration\Escaping;
use OffsetWP\Bundle\TwigBundle\Configuration\TwigConfig;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function ( ContainerConfigurator $container ): void {
	TwigConfig::create()
		->path( '%kernel.root_path%/mail', 'mail' )
		->autoescape( Escaping::Html )
		->global( 'association', 'Étoile Malraux' )
		->globalService( 'rates', Rates::class )
		->globalLiteral( 'handle', '@offsetwp' )
		->dateFormat( 'd/m/Y' )
		->dateTimezone( 'Europe/Paris' )
		->numberDecimals( 2 )
		->numberDecimalPoint( ',' )
		->numberThousandsSeparator( ' ' )
		->apply( $container );
};
