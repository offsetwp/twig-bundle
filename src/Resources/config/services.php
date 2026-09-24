<?php
/**
 * OffsetWP Twig Bundle
 *
 * The service definitions of this bundle.
 *
 * Nothing here carries the "kernel.autoload" tag, and nothing ever should: services
 * carrying it are built by a compiler pass during compilation, which would construct
 * the environment on every single request.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle
 */

declare( strict_types=1 );

use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\LoaderPass;
use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\OwnershipPass;
use OffsetWP\Bundle\TwigBundle\Environment\CoreSettings;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function ( ContainerConfigurator $container ): void {
	$services = $container->services();

	/*
	 * The built-in template source. It starts with no path at all: the paths come
	 * from the configuration, appended by TwigBundle::loadExtension().
	 *
	 * Its second constructor argument, the root it resolves relative paths against,
	 * is deliberately left alone. Naming the project root there would look tidier and
	 * would be a bug: that root is what Twig strips from a template's path to build
	 * its cache key, so two kernels booted in one request — a mu-plugin and a theme,
	 * the ordinary arrangement here — would both key templates/page.twig as
	 * "templates/page.twig" and the second would silently render the first's
	 * template. Relative paths are refused outright anyway, which is the only other
	 * thing that root decides.
	 */
	$services->set( FilesystemLoader::class )
		->tag( LoaderPass::LOADER_TAG, array( 'priority' => 0 ) )
		->tag( OwnershipPass::OWNED_TAG );

	/*
	 * The two ids of the loader the environment reads from. The loader pass points both
	 * at whichever loader it settles on; until it has run, they name the built-in one.
	 */
	$services->alias( LoaderInterface::class, FilesystemLoader::class );
	$services->alias( LoaderPass::LOADER_ID, FilesystemLoader::class );

	/*
	 * The date and number settings live on the environment's own core extension, out
	 * of reach of a method call on the definition below. This applies them once the
	 * environment exists. The arguments are Twig's own defaults until the
	 * configuration replaces them.
	 */
	$services->set( CoreSettings::class )
		->args(
			array(
				array(
					'format'          => CoreSettings::DEFAULT_DATE_FORMAT,
					'interval_format' => CoreSettings::DEFAULT_INTERVAL_FORMAT,
					'timezone'        => null,
				),
				array(
					'decimals'            => CoreSettings::DEFAULT_DECIMALS,
					'decimal_point'       => CoreSettings::DEFAULT_DECIMAL_POINT,
					'thousands_separator' => CoreSettings::DEFAULT_THOUSANDS_SEPARATOR,
				),
			)
		)
		->tag( OwnershipPass::OWNED_TAG );

	/*
	 * Public on purpose. The container is compiled but never dumped, so a private
	 * service is reachable today and would stop being reachable the day that
	 * changes. The entry point of this bundle depends on both ids resolving.
	 */
	$services->set( Environment::class )
		->args( array( service( LoaderInterface::class ), array() ) )
		->configurator( array( service( CoreSettings::class ), '__invoke' ) )
		->tag( OwnershipPass::OWNED_TAG )
		->public();

	$services->alias( 'twig', Environment::class )
		->public();
};
