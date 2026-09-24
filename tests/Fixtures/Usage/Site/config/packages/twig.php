<?php
/**
 * A fixture project of the OffsetWP Twig Bundle test suite.
 *
 * The file a host actually writes to configure this bundle, in the directory the
 * kernel globs for it.
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
			// The project root, resolved before this bundle sees the value — and it is a
			// key here, which is the half of that resolution worth proving.
			'paths'         => array(
				'%kernel.root_path%/mail' => 'mail',
			),
			'globals'       => array(
				'owner' => 'Éditions Exemple',
			),
			'date'          => array(
				'format'   => 'd/m/Y',
				'timezone' => 'Europe/Paris',
			),
			'number_format' => array(
				'decimals'            => 2,
				'decimal_point'       => ',',
				'thousands_separator' => ' ',
			),
		)
	);
};
