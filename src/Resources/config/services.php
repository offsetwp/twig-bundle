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
use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\RuntimePass;
use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\SafeClassPass;
use OffsetWP\Bundle\TwigBundle\Environment\CoreSettings;
use OffsetWP\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;
use Twig\Runtime\EscaperRuntime;

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
	 * The environment is defined under "twig" and its class name is the alias, not the
	 * other way round. Extensions written for other Twig integrations look for a
	 * definition under that id and edit it, and hasDefinition() and getDefinition() do
	 * not follow an alias: with the two swapped, their compiler passes found nothing
	 * and said nothing. Autowiring follows an alias, so a constructor type-hinted on
	 * the class receives this same service.
	 *
	 * Both are public on purpose. The container is compiled but never dumped, so a
	 * private service is reachable today and would stop being reachable the day that
	 * changes. The entry point of this bundle depends on both ids resolving.
	 */
	$services->set( TwigBundle::ENVIRONMENT_ID, Environment::class )
		->args( array( service( LoaderInterface::class ), array() ) )
		->configurator( array( service( CoreSettings::class ), '__invoke' ) )
		->tag( OwnershipPass::OWNED_TAG )
		->public();

	$services->alias( Environment::class, TwigBundle::ENVIRONMENT_ID )
		->public();

	/*
	 * Twig's escaper runtime, defined here rather than left to Twig. The safe-class pass
	 * needs a definition to add the marked classes to, and extensions written for other
	 * Twig integrations reach the escaper under this id. The runtime tag is what hands
	 * it to Twig: the environment asks the runtime loaders it was given before the one
	 * it builds for itself, so this is the escaper every template uses.
	 *
	 * The charset is Twig's own default until the configuration replaces it.
	 */
	$services->set( SafeClassPass::ESCAPER_ID, EscaperRuntime::class )
		->args( array( 'UTF-8' ) )
		->tag( RuntimePass::RUNTIME_TAG )
		->tag( OwnershipPass::OWNED_TAG );
};
