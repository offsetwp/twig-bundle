<?php
/**
 * A fixture project of the OffsetWP Twig Bundle test suite.
 *
 * A project running a bundle written for another Twig integration, next to this one.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Usage
 */

declare( strict_types=1 );

use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Bundle\ForeignBundle;
use OffsetWP\Bundle\TwigBundle\TwigBundle;

return array(
	TwigBundle::class    => array( 'all' => true ),
	ForeignBundle::class => array( 'all' => true ),
);
