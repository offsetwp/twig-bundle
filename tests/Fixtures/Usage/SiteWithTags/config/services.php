<?php
/**
 * A fixture project of the OffsetWP Twig Bundle test suite.
 *
 * The same classes as the project next door, wired the other way: autoconfiguration
 * off, and every point of entry named by a tag written by hand. The README says every
 * one of them still works this way, which is the promise this file is here to keep.
 *
 * The tags are written as the literal strings a host writes rather than as class
 * constants, because a host has no reason to import a compiler pass.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Usage
 */

declare( strict_types=1 );

use App\Twig\AnnouncementLoader;
use App\Twig\GreetTokenParser;
use App\Twig\MembershipRuntime;
use App\Twig\PriceExtension;
use App\Twig\Rates;
use App\Twig\SiteExtension;
use App\Twig\VisitedTemplates;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function ( ContainerConfigurator $container ): void {
	$services = $container->services();

	$services
		->defaults()
			->autowire()
			->public();

	$services->set( Rates::class );

	$services->set( SiteExtension::class )
		->tag( 'twig.extension', array( 'priority' => 10 ) );

	$services->set( PriceExtension::class )
		->tag( 'twig.attribute_extension' )
		->tag( 'twig.runtime' );

	$services->set( MembershipRuntime::class )
		->tag( 'twig.runtime' );

	$services->set( GreetTokenParser::class )
		->tag( 'twig.token_parser' );

	$services->set( VisitedTemplates::class )
		->tag( 'twig.node_visitor' );

	// Higher than the filesystem loader, which carries zero, so this one answers first.
	$services->set( AnnouncementLoader::class )
		->args(
			array(
				array( 'notice.twig' => 'from the loader' ),
			)
		)
		->tag( 'twig.loader', array( 'priority' => 10 ) );
};
