<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Markup
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Markup;

/**
 * A piece of markup that prints itself, the shape of a bag of HTML attributes or of a
 * fragment rendered ahead of time.
 *
 * Not final, unlike every other fixture here: a subclass is how the suite shows that a
 * class marked safe passes the mark on.
 */
class HtmlSnippet implements \Stringable {

	/**
	 * Constructor.
	 *
	 * @param string $html The markup, already escaped for HTML.
	 * @return void
	 */
	public function __construct( private string $html ) {
	}

	/**
	 * The markup, as it stands.
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->html;
	}
}
