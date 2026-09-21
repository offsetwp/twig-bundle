<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Integration
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Integration;

use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\RuntimePass;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\KernelTestCase;
use OffsetWP\Bundle\TwigBundle\TwigBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use Twig\Extra\Cache\CacheExtension;
use Twig\Extra\CssInliner\CssInlinerExtension;
use Twig\Extra\Html\HtmlExtension;
use Twig\Extra\Inky\InkyExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Error\RuntimeError;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extra\Markdown\MarkdownExtension;
use Twig\Extra\Markdown\MarkdownRuntime;
use Twig\Extra\String\StringExtension;

/**
 * The twig/*-extra packages, each installed and rendered.
 *
 * Every case is guarded: the suite passes with none of these packages present, which is
 * the same thing as saying this bundle needs none of them. Nothing under src/ names one,
 * and a test in the unit suite asserts that too.
 */
#[CoversClass( TwigBundle::class )]
final class ExtraPackagesTest extends KernelTestCase {
	/**
	 * The fragment cache writes to disk, so every case starts from nothing.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->removeFragmentCache();
	}

	/**
	 * And leaves nothing behind, whichever way it ended.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->removeFragmentCache();

		parent::tearDown();
	}

	/**
	 * Delete the directory the host's cache pool writes into.
	 *
	 * @return void
	 */
	private function removeFragmentCache(): void {
		( new Filesystem() )->remove( $this->project( 'ExtraProject' ) . '/var' );
	}

	/**
	 * Skip a case whose package is not installed, or whose PHP extension is not
	 * enabled.
	 *
	 * The second half is not theoretical. twig/intl-extra requires php, twig/twig and
	 * symfony/intl, and none of the three brings NumberFormatter, which is what its
	 * extension actually calls. On a machine without ext-intl the package installs,
	 * the class exists, and this case used to run and die on "Class NumberFormatter
	 * not found" — a failure about the machine, reported as a failure of this bundle.
	 * The README says as much about intl already; the suite now says it too.
	 *
	 * @param string      $probe     A class the package ships.
	 * @param string      $package   The Composer package name.
	 * @param string|null $extension A PHP extension the package needs and cannot declare.
	 * @return void
	 */
	private function requirePackage( string $probe, string $package, ?string $extension = null ): void {
		if ( ! class_exists( $probe ) ) {
			$this->markTestSkipped( sprintf( 'The "%s" package is not installed.', $package ) );
		}

		if ( null !== $extension && ! extension_loaded( $extension ) ) {
			$this->markTestSkipped( sprintf( 'The "%s" package needs PHP\'s "%s" extension, which is not enabled.', $package, $extension ) );
		}
	}

	/**
	 * Render one of the fixtures of the project that exercises these packages.
	 *
	 * @param string               $template    The template to render.
	 * @param array<string, mixed> $twig_config The configuration a host would write.
	 * @return string
	 */
	private function renderExtra( string $template, array $twig_config = array() ): string {
		return $this->twig( $this->boot( $twig_config, null, 'ExtraProject' ) )->render( $template );
	}

	/**
	 * Everything a locale puts between digits — thin spaces, non-breaking spaces —
	 * changes with the version of the library underneath. What it puts there is not
	 * what these tests are about.
	 *
	 * @param string $value The rendered value.
	 * @return string
	 */
	private function withoutSpaces( string $value ): string {
		return (string) preg_replace( '/\p{Z}|\s/u', '', $value );
	}

	/**
	 * One line of configuration, and the whole intl toolbox is available. The bundle
	 * knows nothing about this package; it is an extension class like any other.
	 *
	 * @return void
	 */
	public function testIntlExtraWorksWithOneConfigurationLine(): void {
		$this->requirePackage( IntlExtension::class, 'twig/intl-extra', 'intl' );

		$rendered = $this->renderExtra( 'intl.twig', array( 'extensions' => array( IntlExtension::class ) ) );

		$this->assertSame( '1234,50€', $this->withoutSpaces( $rendered ) );
	}

	/**
	 * The same single line, for a package that does something else entirely.
	 *
	 * @return void
	 */
	public function testStringExtraWorksWithOneConfigurationLine(): void {
		$this->requirePackage( StringExtension::class, 'twig/string-extra' );

		$this->assertSame(
			"Un-Ete\n",
			$this->renderExtra( 'string.twig', array( 'extensions' => array( StringExtension::class ) ) )
		);
	}

	/**
	 * Still one line, for the only one of the five whose extension class takes a
	 * constructor argument. It is optional, so autowiring fills it when a matching
	 * service exists and leaves it alone otherwise.
	 *
	 * @return void
	 */
	public function testHtmlExtraWorksWithOneConfigurationLine(): void {
		$this->requirePackage( HtmlExtension::class, 'twig/html-extra' );

		$this->assertSame(
			"a b\n",
			$this->renderExtra( 'html.twig', array( 'extensions' => array( HtmlExtension::class ) ) )
		);
	}

	/**
	 * A tag rather than a filter this time, and still one configuration line. The
	 * package rewrites the whole document, so the assertion is on the rule that moved
	 * rather than on the wrapper around it.
	 *
	 * @return void
	 */
	public function testCssInlinerExtraWorksWithOneConfigurationLine(): void {
		$this->requirePackage( CssInlinerExtension::class, 'twig/cssinliner-extra' );

		$this->assertStringContainsString(
			'<p style="color: red;">Bonjour</p>',
			$this->renderExtra( 'cssinliner.twig', array( 'extensions' => array( CssInlinerExtension::class ) ) )
		);
	}

	/**
	 * The last of the five. An Inky row becomes the table layout an email client will
	 * actually render, and the bundle did nothing but pass the extension along.
	 *
	 * @return void
	 */
	public function testInkyExtraWorksWithOneConfigurationLine(): void {
		$this->requirePackage( InkyExtension::class, 'twig/inky-extra' );

		$this->assertStringContainsString(
			'<table class="row">',
			$this->renderExtra( 'inky.twig', array( 'extensions' => array( InkyExtension::class ) ) )
		);
	}

	/**
	 * The first of the two that take three lines instead of one. Its runtime class
	 * has a constructor argument no autowiring can guess — which implementation of
	 * Markdown you want — so the host declares it.
	 *
	 * The recipe lives in the fixture project's own config/services.php, written the
	 * way the README gives it.
	 *
	 * @return void
	 */
	public function testMarkdownExtraWorksWithTheDocumentedRecipe(): void {
		$this->requirePackage( MarkdownExtension::class, 'twig/markdown-extra' );

		$this->assertStringContainsString( '<h1>Titre</h1>', $this->renderExtra( 'markdown.twig' ) );
	}

	/**
	 * And the reason the third line of that recipe is not optional. MarkdownRuntime
	 * implements no interface at all, so autoconfiguration cannot see it; without the
	 * tag written by hand the filter compiles and then finds nothing to call.
	 *
	 * This is why the runtime tag stays hand-placeable in this bundle rather than
	 * being left to the interface.
	 *
	 * @return void
	 */
	public function testTheMarkdownRuntimeIsNotFoundWithoutItsTag(): void {
		$this->requirePackage( MarkdownExtension::class, 'twig/markdown-extra' );

		$kernel = $this->boot(
			array(),
			static function ( ContainerBuilder $container ): void {
				$container->getDefinition( MarkdownRuntime::class )->clearTag( RuntimePass::RUNTIME_TAG );
			},
			'ExtraProject'
		);

		$this->expectException( RuntimeError::class );
		$this->expectExceptionMessage( 'Unable to load the "Twig\\Extra\\Markdown\\MarkdownRuntime" runtime' );

		$this->twig( $kernel )->render( 'markdown.twig' );
	}

	/**
	 * The second of the two three-line recipes. Its runtime takes a tag-aware cache
	 * pool, and this bundle ships no cache system, so the pool is the host's to
	 * declare — which is the whole shape of the recipe.
	 *
	 * The proof that the fragment really is cached is that the second render ignores
	 * the context it was given and hands back what the first one produced.
	 *
	 * @return void
	 */
	public function testCacheExtraWorksWithTheDocumentedRecipe(): void {
		$this->requirePackage( CacheExtension::class, 'twig/cache-extra' );

		$twig = $this->twig( $this->boot( array(), null, 'ExtraProject' ) );

		$this->assertSame( 'premier', $twig->render( 'cache.twig', array( 'name' => 'premier' ) ) );
		$this->assertSame( 'premier', $twig->render( 'cache.twig', array( 'name' => 'second' ) ) );
	}

	/**
	 * Two things called a cache, and they have nothing to do with each other. Twig's
	 * own cache option stores compiled templates and is off here; the fragment cache
	 * stores rendered output and is writing. The names invite the confusion, so it is
	 * asserted rather than described.
	 *
	 * @return void
	 */
	public function testTheFragmentCacheIsUnrelatedToTheCompilationCache(): void {
		$this->requirePackage( CacheExtension::class, 'twig/cache-extra' );

		$twig = $this->twig( $this->boot( array(), null, 'ExtraProject' ) );

		$this->assertFalse( $twig->getCache() );

		$twig->render( 'cache.twig', array( 'name' => 'premier' ) );

		$this->assertFalse( $twig->getCache() );
		$this->assertDirectoryExists( $this->project( 'ExtraProject' ) . '/var/cache/twig-fragments' );
	}
}
