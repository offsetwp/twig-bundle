<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Bundle
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Bundle;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The compiler pass of that bundle, in the shape such passes take.
 *
 * It asks whether a definition sits under "twig", and edits it if one does. Neither
 * call follows an alias, so with the environment defined under its class name both
 * came back empty: the pass returned, and nothing anywhere said it had.
 */
final class ForeignPass implements CompilerPassInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerBuilder $container The service container.
	 * @return void
	 */
	public function process( ContainerBuilder $container ): void {
		if ( ! $container->hasDefinition( 'twig' ) ) {
			return;
		}

		$container->getDefinition( 'twig' )->addMethodCall( 'addGlobal', array( 'foreign', 'reached' ) );
	}
}
