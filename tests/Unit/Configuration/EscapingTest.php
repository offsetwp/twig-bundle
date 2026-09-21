<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Unit\Configuration
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Unit\Configuration;

use OffsetWP\Bundle\TwigBundle\Configuration\Escaping;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The escaping strategies, measured against Twig rather than read off its source.
 *
 * An enumeration of somebody else's strings is a copy, and a copy drifts. These cases
 * are asserted to be strategies Twig accepts as a default and to escape differently
 * from one another — so a Twig release renaming or dropping one is a red build here
 * rather than a project escaping the wrong way in silence.
 */
#[CoversClass( Escaping::class )]
final class EscapingTest extends TestCase {
	/**
	 * Every case, with what Twig makes of one value that exercises all of them.
	 *
	 * @return array<string, array{Escaping, string}>
	 */
	public static function strategies(): array {
		return array(
			'html'              => array( Escaping::Html, '&lt;a b&gt;' ),
			'js'                => array( Escaping::Js, '\u003Ca\u0020b\u003E' ),
			'css'               => array( Escaping::Css, '\3C a\20 b\3E ' ),
			'url'               => array( Escaping::Url, '%3Ca%20b%3E' ),
			'html attribute'    => array( Escaping::HtmlAttribute, '&lt;a&#x20;b&gt;' ),
			'relaxed attribute' => array( Escaping::HtmlAttributeRelaxed, '&lt;a&#x20;b&gt;' ),
			'by template name'  => array( Escaping::ByTemplateName, '&lt;a b&gt;' ),
		);
	}

	/**
	 * Each case is a strategy Twig accepts as the default one, and each produces what
	 * its name promises.
	 *
	 * Every case renders a template of its own name. A compiled template is named after
	 * a hash that does not include "autoescape", so two environments differing only in
	 * that option would share one compiled class within this process and the first case
	 * would answer for all the others.
	 *
	 * @param Escaping $strategy The case under test.
	 * @param string   $expected What Twig escapes "<a b>" into under it.
	 * @return void
	 */
	#[DataProvider( 'strategies' )]
	public function testEveryCaseIsAStrategyTwigAcceptsAsTheDefault( Escaping $strategy, string $expected ): void {
		$name = $strategy->value . '.twig';

		$twig = new Environment(
			new ArrayLoader( array( $name => '{{ value }}' ) ),
			array( 'autoescape' => $strategy->value )
		);

		$this->assertSame( $expected, $twig->render( $name, array( 'value' => '<a b>' ) ) );
	}

	/**
	 * And the one case whose name says it decides per template really does: the same
	 * value renders raw from a ".txt.twig" and escaped from anything else.
	 *
	 * @return void
	 */
	public function testByTemplateNameDecidesFromTheExtension(): void {
		$twig = new Environment(
			new ArrayLoader(
				array(
					'by-name.txt.twig'  => '{{ value }}',
					'by-name.html.twig' => '{{ value }}',
				)
			),
			array( 'autoescape' => Escaping::ByTemplateName->value )
		);

		$this->assertSame( '<a b>', $twig->render( 'by-name.txt.twig', array( 'value' => '<a b>' ) ) );
		$this->assertSame( '&lt;a b&gt;', $twig->render( 'by-name.html.twig', array( 'value' => '<a b>' ) ) );
	}
}
