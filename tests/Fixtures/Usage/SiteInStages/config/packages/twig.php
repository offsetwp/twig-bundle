<?php
/**
 * A fixture project of the OffsetWP Twig Bundle test suite.
 *
 * The configuration every environment starts from.
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
				__DIR__ . '/../../base-templates' => 'base',
			),
			'globals' => array(
				'mode'   => 'base',
				'shared' => 'from base',
			),
			'date'    => array(
				'format' => 'Y-m-d',
			),
		)
	);
};
