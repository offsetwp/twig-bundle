<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * The services of the fixture project that exercises the twig/*-extra packages.
 *
 * Every recipe here is the one the README gives, written the way a host writes it and
 * guarded by class_exists() so that the suite still passes when a package is absent —
 * which is also what proves this bundle needs none of them.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures
 */

declare( strict_types=1 );

use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Twig\Extra\Cache\CacheExtension;
use Twig\Extra\Cache\CacheRuntime;
use Twig\Extra\Markdown\DefaultMarkdown;
use Twig\Extra\Markdown\MarkdownExtension;
use Twig\Extra\Markdown\MarkdownRuntime;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function ( ContainerConfigurator $container ): void {
	$services = $container->services();

	$services
		->defaults()
			->autowire()
			->autoconfigure()
			->public();

	/*
	 * twig/markdown-extra, the recipe the README gives, verbatim. The runtime tag is
	 * written by hand because MarkdownRuntime implements no interface at all, so
	 * autoconfiguration cannot see it.
	 */
	if ( class_exists( MarkdownExtension::class ) ) {
		$services->set( MarkdownExtension::class )->tag( 'twig.extension' );
		$services->set( DefaultMarkdown::class );
		$services->set( MarkdownRuntime::class )
			->args( array( service( DefaultMarkdown::class ) ) )
			->tag( 'twig.runtime' );
	}

	/*
	 * twig/cache-extra, the recipe the README gives, verbatim. This bundle ships no
	 * cache system, so the pool is the host's to declare — and the runtime tag is
	 * again written by hand, for the same reason as above.
	 */
	if ( class_exists( CacheExtension::class ) && class_exists( FilesystemTagAwareAdapter::class ) ) {
		$services->set( 'app.twig_cache', FilesystemTagAwareAdapter::class )
			->args( array( 'twig', 0, '%kernel.root_path%/var/cache/twig-fragments' ) );

		$services->set( CacheExtension::class )->tag( 'twig.extension' );
		$services->set( CacheRuntime::class )
			->args( array( service( 'app.twig_cache' ) ) )
			->tag( 'twig.runtime' );
	}
};
