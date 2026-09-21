<?php
/**
 * A fixture project of the OffsetWP Twig Bundle test suite.
 *
 * A bundle list that turns this one on in production and nowhere else, which is a
 * shape config/bundles.php supports and nothing had ever exercised.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Usage
 */

declare( strict_types=1 );

use OffsetWP\Bundle\TwigBundle\TwigBundle;

return array(
	TwigBundle::class => array( 'production' => true ),
);
