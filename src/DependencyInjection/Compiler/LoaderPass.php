<?php
/**
 * OffsetWP Twig Bundle
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler;

use OffsetWP\Bundle\TwigBundle\Loader\NoTemplateSourceLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Environment;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;

/**
 * Decides which loader the environment receives.
 *
 * This is the only place that can decide it, because it is the only place that sees
 * every tagged loader at once. Exactly one loader is used directly — no chain, which
 * keeps stack traces short and Twig's own "looked into" message precise. Several are
 * chained in priority order. None at all leaves the stand-in that raises when a
 * template is finally asked for.
 *
 * The built-in filesystem loader is dropped when the configuration gave it no path,
 * which is what makes replacing it entirely a matter of configuring none.
 *
 * A service tagged as a loader whose class cannot load anything is refused here
 * rather than at the first render, where the failure would be a type error from
 * inside Twig naming nothing a reader can act on.
 */
final class LoaderPass implements CompilerPassInterface {

	use TaggedServicesTrait;

	/**
	 * The tag a template source carries.
	 *
	 * @var string
	 */
	public const LOADER_TAG = 'twig.loader';

	/**
	 * The id of the chain this pass builds when several loaders are registered.
	 *
	 * @var string
	 */
	public const CHAIN_ID = 'twig.loader.chain';

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerBuilder $container The service container.
	 * @throws \InvalidArgumentException When a tagged service is not a loader, or holds the id this pass needs.
	 * @return void
	 */
	public function process( ContainerBuilder $container ): void {
		if ( ! $container->hasDefinition( Environment::class ) ) {
			return;
		}

		$this->dropTheFilesystemLoaderWhenItHasNoPaths( $container );

		$loaders = $this->taggedServices( $container, self::LOADER_TAG, LoaderInterface::class );

		$this->assertNoneHoldsTheInterfaceId( $loaders );

		if ( array() === $loaders ) {
			$container->setAlias( LoaderInterface::class, NoTemplateSourceLoader::class );

			return;
		}

		if ( 1 === count( $loaders ) ) {
			$container->setAlias( LoaderInterface::class, $loaders[0]['id'] );

			return;
		}

		$references = array();

		foreach ( $loaders as $loader ) {
			$references[] = new Reference( $loader['id'] );
		}

		$container->setDefinition( self::CHAIN_ID, new Definition( ChainLoader::class, array( $references ) ) );
		$container->setAlias( LoaderInterface::class, self::CHAIN_ID );
	}

	/**
	 * Refuses a loader registered under the interface id this pass points at whatever
	 * it decides.
	 *
	 * That id is this bundle's handle on the loader, not a slot a host fills. A
	 * definition sitting there is either aliased to itself — the library refuses that
	 * outright, with a message about a circular reference and nothing about Twig — or,
	 * in a chain, deleted by the alias that replaces it while the chain still holds a
	 * reference to it.
	 *
	 * @param list<array{id: string, class: string, priority: int, source: string|null}> $loaders The tagged loaders.
	 * @throws \InvalidArgumentException When one of them holds the interface id.
	 * @return void
	 */
	private function assertNoneHoldsTheInterfaceId( array $loaders ): void {
		foreach ( $loaders as $loader ) {
			if ( LoaderInterface::class !== $loader['id'] ) {
				continue;
			}

			throw new \InvalidArgumentException(
				sprintf(
					'The loader "%s" is registered under the id "%s", which this bundle aliases to whichever loader it decides on. Register it under its own id, its class name for instance, and it is picked up the same way.',
					$loader['class'],
					LoaderInterface::class
				)
			);
		}
	}

	/**
	 * Takes the loader tag off the built-in filesystem loader when the configuration
	 * gave it no path.
	 *
	 * A loader with no path answers every template with "there are no registered
	 * paths", which in a chain would only lengthen the message. Untagging it is what
	 * makes replacing it entirely a matter of configuring no path at all.
	 *
	 * The tag goes and the definition stays. Removing the definition outright left any
	 * host service holding a reference to it pointing at nothing, and made a global
	 * written as "@Twig\Loader\FilesystemLoader" report a service that is not defined
	 * — which was true only because this pass had deleted it two passes earlier. An
	 * untagged private definition that nothing references is dropped by the library's
	 * own pass anyway, so nothing is kept that was not wanted.
	 *
	 * What counts as "no path" is the absence of an addPath call, not the absence of
	 * every call: a host adding any other call to this definition was keeping a
	 * path-less loader alive.
	 *
	 * @param ContainerBuilder $container The service container.
	 * @return void
	 */
	private function dropTheFilesystemLoaderWhenItHasNoPaths( ContainerBuilder $container ): void {
		if ( ! $container->hasDefinition( FilesystemLoader::class ) ) {
			return;
		}

		$definition = $container->getDefinition( FilesystemLoader::class );

		foreach ( $definition->getMethodCalls() as $call ) {
			if ( is_array( $call ) && 'addPath' === ( $call[0] ?? null ) ) {
				return;
			}
		}

		$definition->clearTag( self::LOADER_TAG );
	}
}
