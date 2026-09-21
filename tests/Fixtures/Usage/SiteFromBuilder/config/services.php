<?php
/**
 * A fixture project of the OffsetWP Twig Bundle test suite.
 *
 * One service, so that the global naming one has something to name.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Usage
 */

declare( strict_types=1 );

use App\Twig\Rates;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function ( ContainerConfigurator $container ): void {
	$services = $container->services();

	$services
		->defaults()
			->autowire()
			->autoconfigure()
			->public();

	$services->set( Rates::class );
};
