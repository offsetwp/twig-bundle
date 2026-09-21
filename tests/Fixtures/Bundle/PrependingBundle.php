<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Bundle
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Bundle;

use OffsetWP\Framework\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * A bundle of its own that contributes templates, the way a sibling package in this
 * repository already does.
 *
 * It prepends a namespaced path into the twig extension. Whether that survives is not
 * this bundle's decision to make and not one it can see from inside: if prepending
 * ever overwrote the host's own configuration instead of merging with it, every
 * project using both packages would lose its templates at once, silently.
 */
final class PrependingBundle extends Bundle {
	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerConfigurator $container The container configurator.
	 * @param ContainerBuilder      $builder   The service container.
	 * @return void
	 */
	public function prependExtension( ContainerConfigurator $container, ContainerBuilder $builder ): void {
		$container->extension(
			'twig',
			array(
				'paths' => array( __DIR__ . DIRECTORY_SEPARATOR . 'templates' => 'prepended' ),
			)
		);
	}
}
