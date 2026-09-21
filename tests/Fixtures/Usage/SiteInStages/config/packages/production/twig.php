<?php
/**
 * A fixture project of the OffsetWP Twig Bundle test suite.
 *
 * What production adds on top. The kernel imports this after the file above, so this
 * is the second of two configurations the tree has to merge.
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
				__DIR__ . '/../../../prod-templates' => 'prod',
			),
			'globals' => array(
				'mode' => 'production',
			),
			'date'    => array(
				'format' => 'd/m/Y',
			),
		)
	);
};
