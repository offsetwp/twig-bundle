<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension;

use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

/**
 * The two shapes an attributed method can take beyond the obvious one: asking for the
 * environment, and carrying the same attribute twice for two names.
 */
final class TemplateInfoExtension {
	/**
	 * The charset the environment renders with.
	 *
	 * The first parameter is type-hinted as the environment, which is how Twig knows
	 * to pass it rather than expecting it from the template.
	 *
	 * @param Environment $environment The environment, passed by Twig.
	 * @return string
	 */
	#[AsTwigFunction( name: 'charset' )]
	public function charset( Environment $environment ): string {
		return $environment->getCharset();
	}

	/**
	 * Shout a piece of text.
	 *
	 * @param string $text The text to shout.
	 * @return string
	 */
	#[AsTwigFilter( name: 'shout' )]
	#[AsTwigFilter( name: 'yell' )]
	public function shout( string $text ): string {
		return strtoupper( $text ) . '!';
	}
}
