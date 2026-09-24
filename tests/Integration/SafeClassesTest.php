<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Integration
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Integration;

use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\SafeClassPass;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\KernelTestCase;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Markup\Badge;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Markup\HtmlSnippet;
use OffsetWP\Bundle\TwigBundle\TwigBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Environment;

/**
 * Classes marked safe, printed by real templates through a real kernel.
 *
 * Every case prints through autoescaping, because that is the only place Twig reads
 * the mark. A value escaped on purpose, through the escape filter, is escaped whatever
 * its class — and one case is there to say so.
 *
 * The js template ends on a tag, and Twig drops the newline that follows a tag, so it
 * renders without the trailing newline the other two have.
 */
#[CoversClass( SafeClassPass::class )]
#[CoversClass( TwigBundle::class )]
final class SafeClassesTest extends KernelTestCase {

	/**
	 * The markup every case prints, carrying the attribute that shows escaping at a glance.
	 *
	 * @var string
	 */
	private const MARKUP = '<b class="alert">bold</b>';

	/**
	 * The same markup, as the html strategy escapes it.
	 *
	 * @var string
	 */
	private const MARKUP_AS_HTML = '&lt;b class=&quot;alert&quot;&gt;bold&lt;/b&gt;';

	/**
	 * The same markup, as the js strategy escapes it.
	 *
	 * @var string
	 */
	private const MARKUP_AS_JS = '\u003Cb\u0020class\u003D\u0022alert\u0022\u003Ebold\u003C\/b\u003E';

	/**
	 * An environment in which the snippet class is marked safe, or is not marked at all.
	 *
	 * The mark is written as a host writes it, with the literal tag name: that name is
	 * what extensions written for other Twig integrations place, and a renamed constant
	 * would pass a test written with the constant.
	 *
	 * @param string|list<string>|null $strategy    The strategy attribute of the mark, or null for no mark.
	 * @param array<string, mixed>     $twig_config The configuration a host would write.
	 * @return Environment
	 */
	private function environment( string|array|null $strategy, array $twig_config = array() ): Environment {
		return $this->twig(
			$this->boot(
				$twig_config,
				static function ( ContainerBuilder $container ) use ( $strategy ): void {
					if ( null === $strategy ) {
						return;
					}

					$container->register( HtmlSnippet::class, HtmlSnippet::class )
						->addResourceTag( 'twig.safe_class', array( 'strategy' => $strategy ) );
				}
			)
		);
	}

	/**
	 * The case the mark exists for: an object that prints its own markup, printed as it
	 * is by a template escaping for HTML.
	 *
	 * @return void
	 */
	public function testAClassMarkedForHtmlIsPrintedAsItIs(): void {
		$twig = $this->environment( 'html' );

		$this->assertSame( self::MARKUP . "\n", $twig->render( 'safe/html.twig', array( 'value' => new HtmlSnippet( self::MARKUP ) ) ) );
	}

	/**
	 * The mark is made strategy by strategy. Markup escaped for HTML is not escaped for
	 * JavaScript, so inside an autoescape block of another strategy the object is
	 * escaped as it always was.
	 *
	 * @return void
	 */
	public function testAClassMarkedForHtmlIsStillEscapedForAnotherStrategy(): void {
		$twig = $this->environment( 'html' );

		$this->assertSame( self::MARKUP_AS_JS, $twig->render( 'safe/js.twig', array( 'value' => new HtmlSnippet( self::MARKUP ) ) ) );
	}

	/**
	 * A list names several strategies, and each of them is honoured.
	 *
	 * @return void
	 */
	public function testAClassMarkedForSeveralStrategiesIsSafeForEach(): void {
		$twig    = $this->environment( array( 'html', 'js' ) );
		$context = array( 'value' => new HtmlSnippet( self::MARKUP ) );

		$this->assertSame( self::MARKUP . "\n", $twig->render( 'safe/html.twig', $context ) );
		$this->assertSame( self::MARKUP, $twig->render( 'safe/js.twig', $context ) );
	}

	/**
	 * "all" is every strategy at once, so autoescaping never touches the object.
	 *
	 * @return void
	 */
	public function testAClassMarkedForEveryStrategyIsNeverEscaped(): void {
		$twig    = $this->environment( 'all' );
		$context = array( 'value' => new HtmlSnippet( self::MARKUP ) );

		$this->assertSame( self::MARKUP . "\n", $twig->render( 'safe/html.twig', $context ) );
		$this->assertSame( self::MARKUP, $twig->render( 'safe/js.twig', $context ) );
	}

	/**
	 * Without the mark nothing changes: the object is escaped like any other value.
	 *
	 * @return void
	 */
	public function testAnUnmarkedClassIsEscapedAsBefore(): void {
		$twig = $this->environment( null );

		$this->assertSame( self::MARKUP_AS_HTML . "\n", $twig->render( 'safe/html.twig', array( 'value' => new HtmlSnippet( self::MARKUP ) ) ) );
	}

	/**
	 * Twig looks for the mark on the parents of a class as well, so a subclass is safe
	 * wherever its parent is, with no mark of its own.
	 *
	 * @return void
	 */
	public function testASubclassIsSafeWhereItsParentIs(): void {
		$twig = $this->environment( 'html' );

		$this->assertSame( self::MARKUP . "\n", $twig->render( 'safe/html.twig', array( 'value' => new Badge( self::MARKUP ) ) ) );
	}

	/**
	 * Only autoescaping reads the mark. Escaped on purpose, a marked object is escaped
	 * all the same, which is what the README promises about the escape filter.
	 *
	 * @return void
	 */
	public function testTheEscapeFilterEscapesAMarkedClassAllTheSame(): void {
		$twig = $this->environment( 'html' );

		$this->assertSame( self::MARKUP_AS_HTML . "\n", $twig->render( 'safe/explicit.twig', array( 'value' => new HtmlSnippet( self::MARKUP ) ) ) );
	}

	/**
	 * The escaper this bundle defines is the one every template uses, so it has to
	 * escape in the configured charset rather than in Twig's default.
	 *
	 * The js strategy is where the charset shows. It reads its input in that charset,
	 * so in ISO-8859-15 the two bytes of a UTF-8 "é" are two characters and come out as
	 * two escapes. An escaper left at UTF-8 — one the configuration never reached —
	 * would read one character and print one.
	 *
	 * @return void
	 */
	public function testTheEscaperEscapesInTheConfiguredCharset(): void {
		$twig = $this->environment( null, array( 'charset' => 'ISO-8859-15' ) );

		$this->assertSame( '\u00C3\u00A9', $twig->render( 'safe/js.twig', array( 'value' => 'é' ) ) );
	}
}
