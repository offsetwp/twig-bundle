<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Unit\Loader
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Unit\Loader;

use OffsetWP\Bundle\TwigBundle\Loader\NoTemplateSourceLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Error\LoaderError;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

/**
 * The stand-in loader, called the way Twig calls a loader.
 */
#[CoversClass( NoTemplateSourceLoader::class )]
final class NoTemplateSourceLoaderTest extends TestCase {
	/**
	 * The directory the fixture loader reports as missing.
	 *
	 * @var string
	 */
	private const PATH = '/nowhere/templates';

	/**
	 * The message every failing method has to produce.
	 *
	 * @var string
	 */
	private const MESSAGE = 'Twig has no template source. The default directory "/nowhere/templates" does not exist, and there is no "twig.paths" entry and no service tagged "twig.loader". Create that directory, or configure "twig.paths".';

	/**
	 * The loader under test.
	 *
	 * @return NoTemplateSourceLoader
	 */
	private function loader(): NoTemplateSourceLoader {
		return new NoTemplateSourceLoader( self::PATH );
	}

	/**
	 * Every method that has to answer about a source, as a call on the loader.
	 *
	 * @return array<string, array{\Closure}>
	 */
	public static function readingMethods(): array {
		return array(
			'getCacheKey'      => array( static fn ( NoTemplateSourceLoader $loader ): mixed => $loader->getCacheKey( 'page.twig' ) ),
			'getSourceContext' => array( static fn ( NoTemplateSourceLoader $loader ): mixed => $loader->getSourceContext( 'page.twig' ) ),
			'isFresh'          => array( static fn ( NoTemplateSourceLoader $loader ): mixed => $loader->isFresh( 'page.twig', 0 ) ),
		);
	}

	/**
	 * Whichever method Twig reaches for first, the message is the same one. It is
	 * getCacheKey() in the ordinary render — Twig names the compiled class before it
	 * reads anything — but a cached environment, a chain and a freshness check each
	 * arrive somewhere else.
	 *
	 * @param \Closure $call The method under test, as a call on the loader.
	 * @return void
	 */
	#[DataProvider( 'readingMethods' )]
	public function testEveryMethodThatReadsASourceRaisesTheSameFailure( \Closure $call ): void {
		$this->expectException( LoaderError::class );
		$this->expectExceptionMessage( self::MESSAGE );

		$call( $this->loader() );
	}

	/**
	 * Saying a template does not exist is what loses the message: a chain skips past
	 * any loader whose exists() is false, so the reader is told the template is not
	 * defined and never learns that nothing provides templates at all.
	 *
	 * @return void
	 */
	public function testItSaysYesSoThatAChainAsksAndReportsWhatItWasTold(): void {
		$this->assertTrue( $this->loader()->exists( 'page.twig' ) );

		$chain = new ChainLoader( array( new FilesystemLoader( array() ), $this->loader() ) );

		try {
			$chain->getCacheKey( 'page.twig' );
		} catch ( LoaderError $error ) {
			$this->assertStringContainsString( self::MESSAGE, $error->getMessage() );

			return;
		}

		$this->fail( 'The chain was expected to raise.' );
	}
}
