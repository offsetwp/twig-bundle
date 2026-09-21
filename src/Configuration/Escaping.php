<?php
/**
 * OffsetWP Twig Bundle
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Configuration
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Configuration;

/**
 * The escaping strategies Twig ships, as something an editor can complete.
 *
 * The "autoescape" key takes a string, and which strings it takes is knowable only from
 * Twig's own source. Named here, they are one keystroke away and a typo is a compile
 * error rather than an escaping that silently does the wrong thing.
 *
 * A strategy a project registered itself is not in this list and cannot be: the key
 * still accepts a plain string for those.
 */
enum Escaping: string {

	/**
	 * Escapes for an HTML body. Twig's own default.
	 */
	case Html = 'html';

	/**
	 * Escapes for a JavaScript context.
	 */
	case Js = 'js';

	/**
	 * Escapes for a CSS context.
	 */
	case Css = 'css';

	/**
	 * Escapes for a URL component.
	 */
	case Url = 'url';

	/**
	 * Escapes for an unquoted HTML attribute.
	 */
	case HtmlAttribute = 'html_attr';

	/**
	 * Escapes for an HTML attribute, leaving the characters a quoted one tolerates.
	 */
	case HtmlAttributeRelaxed = 'html_attr_relaxed';

	/**
	 * Picks a strategy from each template's own name: a ".txt.twig" renders raw, and
	 * everything else renders as HTML.
	 */
	case ByTemplateName = 'name';
}
