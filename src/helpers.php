<?php
/**
 * OffsetWP Twig Bundle Helpers
 *
 * Three lines of delegation to the facade, guarded the way the framework guards its
 * own helpers. There is no second implementation of anything here.
 *
 * The environment is named in full rather than imported: the facade is imported under
 * the name Twig, so an import of Twig\Environment beside it would read as though the
 * two were related. A fully qualified name cannot be misread.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle
 */

declare( strict_types=1 );

use OffsetWP\Bundle\TwigBundle\Twig;

if ( ! function_exists( 'twig' ) ) {
	/**
	 * Render a template, or reach the environment itself.
	 *
	 * Example:
	 * * `echo twig( 'pages/front-page.twig', array( 'name' => 'Jérôme' ) );`
	 * * `twig()->display( 'pages/front-page.twig' );`
	 *
	 * @param string|null          $name    Template name, or null for the environment.
	 * @param array<string, mixed> $context The variables the template reads.
	 * @throws \LogicException When no kernel with this bundle has booted.
	 * @return ($name is null ? \Twig\Environment : string)
	 */
	function twig( ?string $name = null, array $context = array() ): \Twig\Environment|string {
		return null === $name ? Twig::environment() : Twig::render( $name, $context );
	}
}
