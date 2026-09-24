<?php
/**
 * OffsetWP Twig Bundle
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler;

use OffsetWP\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Twig\RuntimeLoader\ContainerRuntimeLoader;

/**
 * Puts the runtime classes of the host where Twig can reach them, and no sooner.
 *
 * This is where the laziness of the whole package comes from. A non-static method
 * carrying one of Twig's attributes compiles to a call on getRuntime( 'Class' ), and
 * Twig answers that through a runtime loader. The loader here reads from a service
 * locator: the services are referenced, so the container never removes them, and they
 * are constructed on the first template call that actually reaches one.
 *
 * Nothing is built when nothing is tagged, and nothing is ever made public: a locator
 * holds references, which is all it needs.
 *
 * The locator answers to class names, which is the whole of what Twig asks it. So a
 * runtime with no class of its own cannot be found, and one class registered as two
 * services cannot be resolved to either — both are refused here rather than left to
 * fail at the first template call, or worse, to pick one of the two at random.
 */
final class RuntimePass implements CompilerPassInterface {

	use TaggedServicesTrait;

	/**
	 * The tag a runtime class carries.
	 *
	 * @var string
	 */
	public const RUNTIME_TAG = 'twig.runtime';

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerBuilder $container The service container.
	 * @throws \InvalidArgumentException When a runtime has no class, or one class has two services.
	 * @return void
	 */
	public function process( ContainerBuilder $container ): void {
		if ( ! $container->hasDefinition( TwigBundle::ENVIRONMENT_ID ) ) {
			return;
		}

		$runtimes = array();

		foreach ( $this->taggedServices( $container, self::RUNTIME_TAG ) as $service ) {
			if ( '' === $service['class'] ) {
				throw new \InvalidArgumentException(
					sprintf(
						'The service "%s" is tagged "%s" but has no class of its own. Twig asks a runtime loader for a class name, so a runtime built by a factory has to declare the class it builds.',
						$service['id'],
						self::RUNTIME_TAG
					)
				);
			}

			if ( isset( $runtimes[ $service['class'] ] ) ) {
				throw new \InvalidArgumentException(
					sprintf(
						'The Twig runtime "%s" is registered as two services, "%s" and "%s". Twig asks for a runtime by class name and can only be handed one of them; keep one.',
						$service['class'],
						(string) $runtimes[ $service['class'] ],
						$service['id']
					)
				);
			}

			$runtimes[ $service['class'] ] = new Reference( $service['id'] );
		}

		if ( array() === $runtimes ) {
			return;
		}

		$container->getDefinition( TwigBundle::ENVIRONMENT_ID )->addMethodCall(
			'addRuntimeLoader',
			array(
				new Definition(
					ContainerRuntimeLoader::class,
					array( ServiceLocatorTagPass::register( $container, $runtimes ) )
				),
			)
		);
	}
}
