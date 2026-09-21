<?php
/**
 * A fixture project of the OffsetWP Twig Bundle test suite.
 *
 * The project's own configuration, written while a second bundle is prepending one of
 * its own into the same extension.
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
			'paths'   => array(
				__DIR__ . '/../../extra-templates' => 'project',
			),
			'globals' => array(
				'association' => 'Étoile Malraux',
			),
		)
	);
};
