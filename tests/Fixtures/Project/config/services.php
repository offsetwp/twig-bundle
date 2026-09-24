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

use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\MemoryCache;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function ( ContainerConfigurator $container ): void {
	$container->parameters()->set( 'app.name', 'Éditions Exemple' );

	$services = $container->services();

	$services
		->defaults()
			->autowire()
			->autoconfigure()
			->public();

	// A host service the "cache" key can point at. Never built unless something asks.
	$services->set( 'app.twig_cache', MemoryCache::class );
};
