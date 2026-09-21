<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * The services of the fixture project. Autowiring and autoconfiguration are on, and
 * services are public, exactly as the framework's own documentation shows.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures
 */

declare( strict_types=1 );

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function ( ContainerConfigurator $container ): void {
	$container->services()
		->defaults()
			->autowire()
			->autoconfigure()
			->public();
};
