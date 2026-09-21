<?php
/**
 * OffsetWP Twig Bundle
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Attribute\AsTwigTest;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Extension\ExtensionInterface;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\TokenParser\TokenParserInterface;

/**
 * Turns the tagged extensions of the host into method calls on the environment.
 *
 * The pass runs in the before-optimization phase at the default priority, which puts
 * it after the library's own tag resolution: by the time it reads a tag, the classes
 * are resolved and everything autoconfiguration had to add has been added.
 *
 * It appends method calls rather than building anything, so nothing here constructs a
 * single extension. The container builds them when it builds the environment, and the
 * environment is only built when something asks for it.
 *
 * It also refuses what would fail later and worse: the same extension class arriving
 * twice, an attributed method Twig could not call, and a global pointing at a service
 * that was never declared.
 *
 * On the duplicate. Twig refuses it too, at
 * runtime, with a message naming neither source — which is a long evening for whoever
 * has to find out which two registrations collided.
 */
final class ExtensionPass implements CompilerPassInterface {

	use TaggedServicesTrait;

	/**
	 * The tag a full Twig extension carries.
	 *
	 * @var string
	 */
	public const EXTENSION_TAG = 'twig.extension';

	/**
	 * The tag a token parser carries, which is what makes a tag one class.
	 *
	 * @var string
	 */
	public const TOKEN_PARSER_TAG = 'twig.token_parser';

	/**
	 * The tag a node visitor carries.
	 *
	 * @var string
	 */
	public const NODE_VISITOR_TAG = 'twig.node_visitor';

	/**
	 * The tag a class carrying one of Twig's attributes receives.
	 *
	 * Applied by autoconfiguration and never written by a host, which is why the
	 * README does not document it: there is nothing a project can do with it.
	 *
	 * @var string
	 */
	public const ATTRIBUTE_EXTENSION_TAG = 'twig.attribute_extension';

	/**
	 * The three attributes Twig ships, which are the primary way in.
	 *
	 * @var array<int, class-string>
	 */
	public const ATTRIBUTES = array( AsTwigFilter::class, AsTwigFunction::class, AsTwigTest::class );

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerBuilder $container The service container.
	 * @throws \InvalidArgumentException When a registration cannot work.
	 * @return void
	 */
	public function process( ContainerBuilder $container ): void {
		if ( ! $container->hasDefinition( Environment::class ) ) {
			return;
		}

		$twig = $container->getDefinition( Environment::class );

		$this->assertGlobalsResolve( $container, $twig );
		$this->assertCacheResolves( $container, $twig );
		$this->addExtensions( $container, $twig );
		$this->append( $container, $twig, self::TOKEN_PARSER_TAG, TokenParserInterface::class, 'addTokenParser' );
		$this->append( $container, $twig, self::NODE_VISITOR_TAG, NodeVisitorInterface::class, 'addNodeVisitor' );
	}

	/**
	 * Refuses a global pointing at a service the host never declared.
	 *
	 * The check cannot happen where the globals are configured: the configuration of
	 * an extension is loaded into a container of its own, which holds none of the
	 * host's services. Here everything has been merged, so the question has an answer.
	 *
	 * Left alone, the container would still refuse to build, with a message about a
	 * dependency of the environment and nothing about which global asked for it.
	 *
	 * @param ContainerBuilder $container The service container.
	 * @param Definition       $twig      The environment definition.
	 * @throws \InvalidArgumentException When a global references an unknown service.
	 * @return void
	 */
	private function assertGlobalsResolve( ContainerBuilder $container, Definition $twig ): void {
		foreach ( $twig->getMethodCalls() as $call ) {
			if ( ! is_array( $call ) || 'addGlobal' !== ( $call[0] ?? null ) ) {
				continue;
			}

			$arguments = is_array( $call[1] ?? null ) ? $call[1] : array();
			$value     = $arguments[1] ?? null;

			if ( ! $value instanceof Reference || $container->has( (string) $value ) ) {
				continue;
			}

			throw new \InvalidArgumentException(
				sprintf(
					'The Twig global "%s" references the service "%s", which is not defined.',
					is_string( $arguments[0] ?? null ) ? $arguments[0] : get_debug_type( $arguments[0] ?? null ),
					(string) $value
				)
			);
		}
	}

	/**
	 * Refuses a cache pointing at a service the host never declared.
	 *
	 * Same reason as the globals above, and the same impossibility of checking it
	 * where the value is written. Left alone the container refuses to build on "the
	 * service Twig\Environment has a dependency on a non-existent service", which
	 * names the service that was asked for and nothing about which key asked.
	 *
	 * @param ContainerBuilder $container The service container.
	 * @param Definition       $twig      The environment definition.
	 * @throws \InvalidArgumentException When the cache references an unknown service.
	 * @return void
	 */
	private function assertCacheResolves( ContainerBuilder $container, Definition $twig ): void {
		$options = $twig->getArguments()[1] ?? null;
		$cache   = is_array( $options ) ? ( $options['cache'] ?? null ) : null;

		if ( ! $cache instanceof Reference || $container->has( (string) $cache ) ) {
			return;
		}

		throw new \InvalidArgumentException(
			sprintf(
				'The "twig.cache" key references the service "%s", which is not defined.',
				(string) $cache
			)
		);
	}

	/**
	 * Appends one addExtension call per extension, in the order Twig must receive them.
	 *
	 * Two registers feed this: services tagged as extensions, and classes carrying Twig
	 * attributes, which are wrapped in an extension of Twig's own. They are ordered
	 * together rather than one register after the other, because priority is documented
	 * as deciding which of two declarations of one filter name survives — and that is
	 * only true if the two can be compared. Appended in two blocks, an attributed
	 * method always reached Twig last and therefore always won, whatever priority
	 * either side carried.
	 *
	 * Equal priorities keep the order of declaration, and a tagged service is declared
	 * before an attributed class, so the default arrangement is unchanged: the sort is
	 * stable and neither register moves unless a priority says so.
	 *
	 * @param ContainerBuilder $container The service container.
	 * @param Definition       $twig      The environment definition.
	 * @throws \InvalidArgumentException When one extension class is registered twice.
	 * @return void
	 */
	private function addExtensions( ContainerBuilder $container, Definition $twig ): void {
		$extensions = array_merge(
			$this->taggedExtensionServices( $container ),
			$this->attributeExtensions( $container )
		);

		usort( $extensions, static fn ( array $a, array $b ): int => $b['priority'] <=> $a['priority'] );

		foreach ( $extensions as $extension ) {
			$twig->addMethodCall( 'addExtension', array( $extension['argument'] ) );
		}
	}

	/**
	 * The services tagged as extensions, each as a reference ready to append.
	 *
	 * Twig refuses a duplicate extension at runtime with a message naming neither
	 * source, which is a long evening for whoever has to find the two registrations
	 * that collided. This names both.
	 *
	 * @param ContainerBuilder $container The service container.
	 * @throws \InvalidArgumentException When the same extension class comes from two sources.
	 * @return list<array{priority: int, argument: Reference}>
	 */
	private function taggedExtensionServices( ContainerBuilder $container ): array {
		$found = array();
		$seen  = array();

		foreach ( $this->taggedServices( $container, self::EXTENSION_TAG, ExtensionInterface::class ) as $service ) {
			if ( isset( $seen[ $service['class'] ] ) ) {
				throw new \InvalidArgumentException(
					sprintf(
						'The Twig extension "%s" is registered twice: by %s and by %s. Twig refuses duplicate extensions; keep one.',
						$service['class'],
						$this->describe( $seen[ $service['class'] ] ),
						$this->describe( $service )
					)
				);
			}

			if ( '' !== $service['class'] ) {
				$seen[ $service['class'] ] = $service;
			}

			$found[] = array(
				'priority' => $service['priority'],
				'argument' => new Reference( $service['id'] ),
			);
		}

		return $found;
	}

	/**
	 * Each attributed class wrapped in an extension of Twig's own, ready to append.
	 *
	 * The wrapper holds a class name and nothing else: it reads the attributes the
	 * first time Twig asks it for a filter, and the class itself is reached through
	 * the runtime loader when a template actually calls one. No service is created
	 * for the wrapper, because there is nothing in it worth a service.
	 *
	 * One wrapper per class, never per service: Twig indexes an attribute extension on
	 * the class it wraps and refuses a second one for the same class, with a message
	 * naming neither of the two registrations that collided.
	 *
	 * @param ContainerBuilder $container The service container.
	 * @throws \InvalidArgumentException When a service has no class, a method is not public, or a class is registered twice.
	 * @return list<array{priority: int, argument: Definition}>
	 */
	private function attributeExtensions( ContainerBuilder $container ): array {
		$found = array();
		$seen  = array();

		foreach ( $this->taggedServices( $container, self::ATTRIBUTE_EXTENSION_TAG ) as $service ) {
			if ( '' === $service['class'] ) {
				throw new \InvalidArgumentException(
					sprintf(
						'The service "%s" is tagged "%s" but has no class of its own. Twig reads the attributes off a class name, so a service built by a factory has to declare the class it builds.',
						$service['id'],
						self::ATTRIBUTE_EXTENSION_TAG
					)
				);
			}

			if ( isset( $seen[ $service['class'] ] ) ) {
				throw new \InvalidArgumentException(
					sprintf(
						'The class "%s" carries Twig attributes and is registered as two services, "%s" and "%s". Twig holds one set of attributes per class; keep one of the two.',
						$service['class'],
						$seen[ $service['class'] ],
						$service['id']
					)
				);
			}

			$seen[ $service['class'] ] = $service['id'];

			$this->assertAttributedMethodsArePublic( $container, $service['class'] );

			$found[] = array(
				'priority' => $service['priority'],
				'argument' => new Definition( AttributeExtension::class, array( $service['class'] ) ),
			);
		}

		return $found;
	}

	/**
	 * Refuses a class whose attributed method Twig would find and could not call.
	 *
	 * The container scans public methods only, so a non-public one never gets its
	 * class tagged by itself. Twig's own reader scans every method whatever its
	 * visibility — so on a class that is tagged for some other method, a non-public
	 * one produces a filter that exists and fails at the first call, with a message
	 * about visibility and nothing about Twig.
	 *
	 * The container is asked for the reflection rather than PHP: it answers null for a
	 * class that cannot be loaded instead of raising, and it is the only one that knows
	 * what the class was named before a parameter was resolved in it.
	 *
	 * @param ContainerBuilder $container        The service container.
	 * @param string           $attributed_class The class carrying the attributes.
	 * @throws \InvalidArgumentException When one of its attributed methods is not public.
	 * @return void
	 */
	private function assertAttributedMethodsArePublic( ContainerBuilder $container, string $attributed_class ): void {
		$reflection = $container->getReflectionClass( $attributed_class, false );

		if ( null === $reflection ) {
			return;
		}

		foreach ( $reflection->getMethods() as $method ) {
			if ( $method->isPublic() ) {
				continue;
			}

			foreach ( self::ATTRIBUTES as $attribute ) {
				if ( array() === $method->getAttributes( $attribute ) ) {
					continue;
				}

				throw new \InvalidArgumentException(
					sprintf(
						'"%s::%s()" carries #[%s] but is not public. Twig cannot call it. Make the method public.',
						$reflection->getName(),
						$method->getName(),
						( new \ReflectionClass( $attribute ) )->getShortName()
					)
				);
			}
		}
	}

	/**
	 * Appends one method call per service carrying a tag, in priority order.
	 *
	 * @param ContainerBuilder $container The service container.
	 * @param Definition       $twig      The environment definition.
	 * @param string           $tag       The tag to collect.
	 * @param string           $expected_interface The interface every tagged class must implement.
	 * @param string           $method    The environment method to call.
	 * @throws \InvalidArgumentException When a tagged class does not implement the interface.
	 * @return void
	 */
	private function append( ContainerBuilder $container, Definition $twig, string $tag, string $expected_interface, string $method ): void {
		foreach ( $this->taggedServices( $container, $tag, $expected_interface ) as $service ) {
			$twig->addMethodCall( $method, array( new Reference( $service['id'] ) ) );
		}
	}

	/**
	 * Where a registration came from, in the words a reader needs to find it.
	 *
	 * @param array{id: string, class: string, priority: int, source: string|null} $service The tagged service.
	 * @return string
	 */
	private function describe( array $service ): string {
		if ( null === $service['source'] ) {
			return sprintf( 'the service "%s"', $service['id'] );
		}

		return sprintf( 'the "%s" config key', $service['source'] );
	}
}
