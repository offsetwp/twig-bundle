<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * A project running two bundles, the second of which contributes templates.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures
 */

declare( strict_types=1 );

use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Bundle\PrependingBundle;
use OffsetWP\Bundle\TwigBundle\TwigBundle;

return array(
	TwigBundle::class       => array( 'all' => true ),
	PrependingBundle::class => array( 'all' => true ),
);
