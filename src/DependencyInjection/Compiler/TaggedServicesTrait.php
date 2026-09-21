<?php
/**
 * OffsetWP Twig Bundle
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * What every pass of this bundle does with a tag: read it, order it, check it.
 *
 * The ordering rule is the one the README states — higher priority first, the default
 * priority is zero, and ties keep the order the host declared them in. That last part
 * is why the sort has to be stable, which PHP's own has been since 8.0.
 */
trait TaggedServicesTrait {
	/**
	 * Every service carrying a tag, in the order the environment must receive them.
	 *
	 * One entry per service, never one per tag occurrence, and that is not a detail.
	 * A service can end up carrying the same tag twice through no fault of its own:
	 * autoconfiguration adds the tag with no attributes, and the host that also wrote
	 * the tag by hand to set a priority wrote it with an attribute. The library only
	 * skips an autoconfigured tag whose attributes are identical to one already
	 * there, so the two survive side by side — and that is the arrangement the README
	 * documents, autoconfiguration on and a priority written by hand. Counted twice,
	 * one loader appears twice in the chain, one node visitor walks every node twice,
	 * and one extension is refused as a duplicate of itself.
	 *
	 * Of two occurrences the higher priority wins, which is the only reading that
	 * cannot lose an intent: the attribute-less one the container adds says nothing.
	 *
	 * An abstract definition is refused by the library as it collects: a definition
	 * that cannot be instantiated cannot be referenced either, and left to itself the
	 * failure would name the environment rather than the service that carries the tag.
	 *
	 * @param ContainerBuilder $container The service container.
	 * @param string           $tag       The tag to collect.
	 * @param string|null      $expected_interface The interface every tagged class must implement.
	 * @throws \InvalidArgumentException When a tagged service is abstract, or its class does not implement the interface.
	 * @return list<array{id: string, class: string, priority: int, source: string|null}>
	 */
	private function taggedServices( ContainerBuilder $container, string $tag, ?string $expected_interface = null ): array {
		$found = array();

		foreach ( $container->findTaggedServiceIds( $tag, true ) as $id => $tags ) {
			$priorities = array();
			$source     = null;

			foreach ( $tags as $attributes ) {
				$priorities[] = $this->priorityOf( $attributes, $id, $tag );

				if ( null === $source ) {
					$source = $this->sourceOf( $attributes );
				}
			}

			$service = array(
				'id'       => $id,
				'class'    => $this->classOf( $container, $container->getDefinition( $id ) ),
				'priority' => array() === $priorities ? 0 : max( $priorities ),
				'source'   => $source,
			);

			if ( null !== $expected_interface ) {
				$this->assertImplements( $service, $tag, $expected_interface );
			}

			$found[] = $service;
		}

		usort( $found, static fn ( array $a, array $b ): int => $b['priority'] <=> $a['priority'] );

		return $found;
	}

	/**
	 * The class a tagged definition will actually be built from.
	 *
	 * Two shapes reach this point looking classless when they are not, because both
	 * are settled by passes that run after this one.
	 *
	 * A definition declared with its class in a container parameter still holds the
	 * expression. Read raw, it is a class that implements nothing, so a perfectly good
	 * extension was refused for not implementing an interface — naming "%app.class%"
	 * as the class that failed — and a runtime keyed the locator on the expression,
	 * which Twig then could not find under the real class name.
	 *
	 * A definition declared as the child of another carries no class at all: the
	 * parent's is copied down later. Read raw, it is classless, and a runtime that is
	 * nothing but a parent and a few arguments was refused as one built by a factory.
	 *
	 * An empty string is returned for what is genuinely classless — a factory, a
	 * synthetic service — which is the one case the callers are written for.
	 *
	 * @param ContainerBuilder $container  The service container.
	 * @param Definition       $definition The tagged definition.
	 * @return string
	 */
	private function classOf( ContainerBuilder $container, Definition $definition ): string {
		$seen = array();

		while ( null === $definition->getClass() && $definition instanceof ChildDefinition ) {
			$parent = $definition->getParent();

			if ( isset( $seen[ $parent ] ) || ! $container->hasDefinition( $parent ) ) {
				return '';
			}

			$seen[ $parent ] = true;
			$definition      = $container->getDefinition( $parent );
		}

		$class = $definition->getClass();

		if ( null === $class ) {
			return '';
		}

		$resolved = $container->getParameterBag()->resolveValue( $class );

		return is_string( $resolved ) ? $resolved : '';
	}

	/**
	 * Refuses a service carrying a tag its class cannot honour.
	 *
	 * A definition with no class of its own is left alone: it is built by a factory or
	 * declared synthetic, and there is nothing here to check against.
	 *
	 * @param array{id: string, class: string, priority: int, source: string|null} $service   The tagged service.
	 * @param string                                                               $tag       The tag it carries.
	 * @param string                                                               $expected_interface The interface it must implement.
	 * @throws \InvalidArgumentException When the class does not implement the interface.
	 * @return void
	 */
	private function assertImplements( array $service, string $tag, string $expected_interface ): void {
		if ( '' === $service['class'] || is_subclass_of( $service['class'], $expected_interface ) ) {
			return;
		}

		throw new \InvalidArgumentException(
			sprintf(
				'The service "%s" is tagged "%s" but its class "%s" does not implement "%s".',
				$service['id'],
				$tag,
				$service['class'],
				$expected_interface
			)
		);
	}

	/**
	 * The configuration key a tag says it came from, when it says so.
	 *
	 * Only this bundle sets it, on the definitions the "extensions" key produces, and
	 * only so that a failure can name where a registration came from.
	 *
	 * @param mixed $attributes The tag attributes, as the container hands them over.
	 * @return string|null
	 */
	private function sourceOf( mixed $attributes ): ?string {
		if ( ! is_array( $attributes ) || ! isset( $attributes['source'] ) || ! is_string( $attributes['source'] ) ) {
			return null;
		}

		return $attributes['source'];
	}

	/**
	 * The priority one occurrence of a tag carries.
	 *
	 * A priority that is not a whole number is refused rather than read as zero. Zero
	 * is a real priority — it is the one the filesystem loader carries — so reading a
	 * typo as zero means a loader that was meant to come first quietly comes last, and
	 * nothing anywhere says the value was not understood.
	 *
	 * @param mixed  $attributes The tag attributes, as the container hands them over.
	 * @param string $id         The service carrying the tag.
	 * @param string $tag        The tag itself.
	 * @throws \InvalidArgumentException When the priority is not a whole number.
	 * @return int
	 */
	private function priorityOf( mixed $attributes, string $id, string $tag ): int {
		if ( ! is_array( $attributes ) || ! isset( $attributes['priority'] ) ) {
			return 0;
		}

		$priority = $attributes['priority'];

		if ( is_int( $priority ) ) {
			return $priority;
		}

		if ( is_string( $priority ) && 1 === preg_match( '/^-?\d+$/', $priority ) ) {
			return (int) $priority;
		}

		throw new \InvalidArgumentException(
			sprintf(
				'The service "%s" is tagged "%s" with a priority of %s. A priority is a whole number, and higher reaches Twig first.',
				$id,
				$tag,
				get_debug_type( $priority )
			)
		);
	}
}
