<?php
/**
 * A fixture project of the OffsetWP Twig Bundle test suite.
 *
 * The services of the project, written the way a project writes them: one load() over
 * the namespace, autowiring and autoconfiguration on, and nothing tagged by hand.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Usage
 */

declare( strict_types=1 );

use App\Twig\AnnouncementLoader;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function ( ContainerConfigurator $container ): void {
	$services = $container->services();

	$services
		->defaults()
			->autowire()
			->autoconfigure()
			->public();

	$services->load( 'App\\', '../../App/*' )
		->exclude( '../../App/Twig/Node' );

	// A service that needs data rather than dependencies, so it is given its data here.
	$services->set( AnnouncementLoader::class )
		->args(
			array(
				array( 'announcements/notice.twig' => 'The next meeting is on {{ meeting|date }}.' ),
			)
		);
};
