<?php
/**
 * A fixture project of the OffsetWP Twig Bundle test suite.
 *
 * The second project of the request. On this platform a mu-plugin and a theme each
 * boot a kernel, so two projects in one process is the ordinary arrangement and not
 * an edge case.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Usage
 */

declare( strict_types=1 );

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function ( ContainerConfigurator $container ): void {
	$container->extension(
		'twig',
		array(
			'globals' => array(
				'association' => 'Le thème',
			),
		)
	);
};
