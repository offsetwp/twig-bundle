<?php
/**
 * A fixture project of the OffsetWP Twig Bundle test suite.
 *
 * One service of the project's own, asking for Twig by its class and leaving the rest
 * to autowiring.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Usage
 */

declare( strict_types=1 );

use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Usage\Mail\Newsletter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function ( ContainerConfigurator $container ): void {
	$services = $container->services();

	$services
		->defaults()
			->autowire()
			->autoconfigure()
			->public();

	$services->set( Newsletter::class );
};
