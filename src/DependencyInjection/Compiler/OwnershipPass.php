<?php
/**
 * OffsetWP Twig Bundle
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler;

use OffsetWP\Bundle\TwigBundle\Environment\CoreSettings;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Refuses to go on when a project has redefined a service this bundle builds.
 *
 * The library captures a host's own definitions before it merges a bundle's and
 * restores them afterwards, so a definition written in the project's own
 * config/services.php wins over the one of the same id here. Winning means replacing:
 * everything the configuration put on ours — the options, the template paths, the
 * globals, the extensions, the configurator that applies the date and number settings
 * — goes with it, and nothing says so.
 *
 * What is left is a service that builds and answers to the right id, and a twig.*
 * configuration that is read, validated and then thrown away. A project in that state
 * reports its template directory as not configured while the directory sits in the
 * configuration file, which is a long evening.
 *
 * So each of those definitions carries a tag, this pass checks the tag is still there,
 * and a missing one is named along with what to do instead. Taking the id back is not
 * an option: the host's definition may be the one every other service references.
 */
final class OwnershipPass implements CompilerPassInterface {

	/**
	 * The tag every definition this bundle owns carries.
	 *
	 * @var string
	 */
	public const OWNED_TAG = 'twig.owned';

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerBuilder $container The service container.
	 * @throws \LogicException When a definition of this bundle has been replaced.
	 * @return void
	 */
	public function process( ContainerBuilder $container ): void {
		if ( ! $container->hasDefinition( Environment::class ) ) {
			return;
		}

		foreach ( array( Environment::class, FilesystemLoader::class, CoreSettings::class ) as $id ) {
			if ( ! $container->hasDefinition( $id ) || $container->getDefinition( $id )->hasTag( self::OWNED_TAG ) ) {
				continue;
			}

			throw new \LogicException(
				sprintf(
					'The service "%s" is defined by this project as well as by the Twig bundle, and the project\'s definition is the one that survives — with everything the "twig" configuration had put on the bundle\'s: the options, the template paths, the globals and the extensions. Remove it from your config/services.php and configure "twig" instead, or register your own service under an id of your own.',
					$id
				)
			);
		}
	}
}
