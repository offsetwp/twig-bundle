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

/**
 * Tells Twig's escaper which classes print markup that is already escaped.
 *
 * An object a template prints is escaped like any other value, so a bag of HTML
 * attributes that renders itself comes out as class=&quot;alert&quot;. Twig's escaper
 * keeps a list of classes it leaves alone, strategy by strategy, and this pass fills it
 * from the classes marked with the tag below.
 *
 * The mark is a resource tag, not a tag: it says something about a class, and the
 * definition carrying it is never built. Written as an ordinary tag it is refused by
 * the library itself, as the tag is collected, with a message naming the service — so
 * that mistake needs nothing here.
 *
 * Only autoescaping reads the list. A value escaped on purpose, through the escape
 * filter, is escaped whatever its class.
 *
 * The pass runs late — see TwigBundle::build() for why. The one thing that can come
 * before it is a service the kernel builds while the container compiles, one tagged
 * "kernel.autoload": a constructor of that kind rendering a template would build the
 * escaper before any class was marked, and it would stay that way for the request.
 */
final class SafeClassPass implements CompilerPassInterface {

	/**
	 * The resource tag that marks a class safe for one escaping strategy or more.
	 *
	 * @var string
	 */
	public const SAFE_CLASS_TAG = 'twig.safe_class';

	/**
	 * The id of Twig's escaper runtime, the service the marked classes are added to.
	 *
	 * This bundle defines it rather than leaving Twig to build one of its own, for two
	 * reasons: this pass needs a definition to add the classes to, and extensions
	 * written for other Twig integrations reach the escaper under this id.
	 *
	 * @var string
	 */
	public const ESCAPER_ID = 'twig.runtime.escaper';

	/**
	 * What the name of a strategy looks like.
	 *
	 * Every name Twig ships fits it, and so does one a project registers itself, which
	 * makes it the whole of the check. The names Twig ships are listed in the message
	 * only, to say what is expected.
	 *
	 * @var string
	 */
	private const STRATEGY_NAME = '/^[a-z][a-z0-9_]*$/D';

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerBuilder $container The service container.
	 * @throws \InvalidArgumentException When a class is marked with an ordinary tag, or with a strategy that is missing, empty or malformed.
	 * @return void
	 */
	public function process( ContainerBuilder $container ): void {
		if ( ! $container->hasDefinition( self::ESCAPER_ID ) ) {
			return;
		}

		$escaper = $container->getDefinition( self::ESCAPER_ID );

		foreach ( $container->findTaggedResourceIds( self::SAFE_CLASS_TAG ) as $id => $tags ) {
			// The library refuses a resource without a class, and writes the class back resolved.
			$marked_class = (string) $container->getDefinition( $id )->getClass();

			foreach ( $tags as $attributes ) {
				$escaper->addMethodCall( 'addSafeClass', array( $marked_class, $this->strategiesOf( $attributes, $id ) ) );
			}
		}
	}

	/**
	 * The strategies one occurrence of the tag names, as the list Twig's escaper takes.
	 *
	 * One name or a list of them, and nothing else. A class safe for no strategy would be
	 * a mark that does nothing, so a missing attribute and an empty list are refused
	 * rather than read as none at all: "all" is how every strategy is named at once. A
	 * name Twig does not ship is accepted when it is one a project could have registered.
	 *
	 * @param mixed  $attributes The tag attributes, as the container hands them over.
	 * @param string $id         The service carrying the tag.
	 * @throws \InvalidArgumentException When the strategy is missing, empty or malformed.
	 * @return list<string>
	 */
	private function strategiesOf( mixed $attributes, string $id ): array {
		$strategies = is_array( $attributes ) ? ( $attributes['strategy'] ?? null ) : null;

		if ( null === $strategies ) {
			throw new \InvalidArgumentException(
				sprintf(
					'The service "%s" is tagged "%s" without a "strategy" attribute. Name the escaping strategies its class is already escaped for, "html" for instance, or "all" for every one of them.',
					$id,
					self::SAFE_CLASS_TAG
				)
			);
		}

		if ( is_string( $strategies ) ) {
			$strategies = array( $strategies );
		}

		if ( ! is_array( $strategies ) ) {
			throw $this->notStrategyNames( $id, sprintf( 'a "strategy" of type %s', get_debug_type( $strategies ) ) );
		}

		if ( array() === $strategies ) {
			throw new \InvalidArgumentException(
				sprintf(
					'The service "%s" is tagged "%s" with an empty "strategy" list. Name at least one escaping strategy, or "all" for every one of them.',
					$id,
					self::SAFE_CLASS_TAG
				)
			);
		}

		$names = array();

		foreach ( $strategies as $strategy ) {
			if ( ! is_string( $strategy ) ) {
				throw $this->notStrategyNames( $id, sprintf( 'a "strategy" list holding %s', get_debug_type( $strategy ) ) );
			}

			if ( 1 !== preg_match( self::STRATEGY_NAME, $strategy ) ) {
				throw new \InvalidArgumentException(
					sprintf(
						'The service "%s" is tagged "%s" with the strategy "%s". A strategy is one Twig ships — html, js, css, url, html_attr or html_attr_relaxed — "all" for every one of them, or one of your own, named in lowercase letters, digits and underscores and starting with a letter.',
						$id,
						self::SAFE_CLASS_TAG,
						$strategy
					)
				);
			}

			$names[] = $strategy;
		}

		return $names;
	}

	/**
	 * The failure for a "strategy" attribute holding something other than names.
	 *
	 * @param string $id    The service carrying the tag.
	 * @param string $found What was found, in the words the message needs.
	 * @return \InvalidArgumentException
	 */
	private function notStrategyNames( string $id, string $found ): \InvalidArgumentException {
		return new \InvalidArgumentException(
			sprintf(
				'The service "%s" is tagged "%s" with %s. The "strategy" attribute takes one strategy name, or a list of them.',
				$id,
				self::SAFE_CLASS_TAG,
				$found
			)
		);
	}
}
