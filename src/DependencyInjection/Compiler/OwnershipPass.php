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
use OffsetWP\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Refuses to go on when a project has taken the id of a service this bundle builds.
 *
 * The library captures a host's own definitions and aliases before it merges a
 * bundle's, and restores them afterwards — the definitions first, the aliases last — so
 * anything written under the same id in the project's own config/services.php wins over
 * the one here. Winning means replacing, and an alias replaces as surely as a definition
 * does: setting one removes the definition under its id. Everything the configuration
 * put on ours — the options, the template paths, the globals, the extensions, the
 * configurator that applies the date and number settings — goes with it, and nothing
 * says so.
 *
 * What is left is a service that builds and answers to the right id, and a twig.*
 * configuration that is read, validated and then thrown away. A project in that state
 * reports its template directory as not configured while the directory sits in the
 * configuration file, which is a long evening. When the id taken is "twig", every
 * compiler pass that edits the environment — this bundle's and other bundles' alike —
 * finds no definition to edit, and returns without a word.
 *
 * So each of those definitions carries a tag, this pass checks the tag is still there
 * and the id is not an alias, and a failure names what it found along with what to do
 * instead. Taking the id back is not an option: the host's service may be the one every
 * other service references.
 *
 * The class name of the environment is checked the other way round. It is this
 * bundle's alias of "twig", so what is refused there is a definition, or an alias
 * pointing anywhere else.
 */
final class OwnershipPass implements CompilerPassInterface {

	/**
	 * The tag every definition this bundle owns carries.
	 *
	 * @var string
	 */
	public const OWNED_TAG = 'twig.owned';

	/**
	 * The ids this bundle defines a service under, each definition carrying the tag above.
	 *
	 * @var array<int, string>
	 */
	private const OWNED_IDS = array( TwigBundle::ENVIRONMENT_ID, FilesystemLoader::class, CoreSettings::class, SafeClassPass::ESCAPER_ID );

	/**
	 * {@inheritDoc}
	 *
	 * Nothing is checked in a container that holds no environment at all, which is one
	 * compiled without this bundle's services. An alias under the environment's id is
	 * not that case: it is the project's, and it is what removed the environment.
	 *
	 * @param ContainerBuilder $container The service container.
	 * @throws \LogicException When a service of this bundle has been replaced.
	 * @return void
	 */
	public function process( ContainerBuilder $container ): void {
		if ( ! $container->hasDefinition( TwigBundle::ENVIRONMENT_ID ) && ! $container->hasAlias( TwigBundle::ENVIRONMENT_ID ) ) {
			return;
		}

		foreach ( self::OWNED_IDS as $id ) {
			$this->assertStillDefinedHere( $container, $id );
		}

		$this->assertTheClassStillNamesTheEnvironment( $container );
	}

	/**
	 * Refuses an id of this bundle that the project has taken for a service of its own.
	 *
	 * @param ContainerBuilder $container The service container.
	 * @param string           $id        An id this bundle defines a service under.
	 * @throws \LogicException When a definition or an alias of the project holds it.
	 * @return void
	 */
	private function assertStillDefinedHere( ContainerBuilder $container, string $id ): void {
		if ( $container->hasAlias( $id ) ) {
			throw new \LogicException(
				sprintf(
					'The service "%s" is an alias of "%s" in this project, and an alias takes the place of the definition the Twig bundle keeps under that id — with everything the "twig" configuration had put on it: the options, the template paths, the globals and the extensions. Remove the alias from your config/services.php and configure "twig" instead, or give your own service an id of its own.',
					$id,
					(string) $container->getAlias( $id )
				)
			);
		}

		if ( ! $container->hasDefinition( $id ) || $container->getDefinition( $id )->hasTag( self::OWNED_TAG ) ) {
			return;
		}

		throw new \LogicException(
			sprintf(
				'The service "%s" is defined by this project as well as by the Twig bundle, and the project\'s definition is the one that survives — with everything the "twig" configuration had put on the bundle\'s: the options, the template paths, the globals and the extensions. Remove it from your config/services.php and configure "twig" instead, or register your own service under an id of your own.',
				$id
			)
		);
	}

	/**
	 * Refuses a project that has taken the class name of the environment for itself.
	 *
	 * That id is what autowiring reads: every constructor type-hinted on the class
	 * receives whatever it names. A definition of the project's own there, or an alias
	 * pointing anywhere but "twig", hands those constructors an environment that none of
	 * the configuration reached, while twig() renders with this bundle's. An alias of
	 * the project pointing at "twig" says what this bundle says, and is left alone.
	 *
	 * @param ContainerBuilder $container The service container.
	 * @throws \LogicException When the class name no longer names the environment.
	 * @return void
	 */
	private function assertTheClassStillNamesTheEnvironment( ContainerBuilder $container ): void {
		if ( $container->hasDefinition( Environment::class ) ) {
			$taken = 'defined by this project';
		} elseif ( $container->hasAlias( Environment::class ) && TwigBundle::ENVIRONMENT_ID !== (string) $container->getAlias( Environment::class ) ) {
			$taken = sprintf( 'an alias of "%s" in this project', (string) $container->getAlias( Environment::class ) );
		} else {
			return;
		}

		throw new \LogicException(
			sprintf(
				'The service "%s" is %s, while the Twig bundle keeps that id as an alias of "%s". Autowiring hands out whatever it names, so every service type-hinted on the class would receive an environment none of the "twig" configuration reached, while twig() renders with another. Remove it from your config/services.php and configure "twig" instead, or give your own environment an id of its own.',
				Environment::class,
				$taken,
				TwigBundle::ENVIRONMENT_ID
			)
		);
	}
}
