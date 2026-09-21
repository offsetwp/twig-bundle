<?php
/**
 * A fixture project of the OffsetWP Twig Bundle test suite.
 *
 * A project running a second bundle that ships templates of its own, which is the
 * arrangement any package building on this one produces.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Usage
 */

declare( strict_types=1 );

use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Bundle\PrependingBundle;
use OffsetWP\Bundle\TwigBundle\TwigBundle;

return array(
	TwigBundle::class       => array( 'all' => true ),
	PrependingBundle::class => array( 'all' => true ),
);
