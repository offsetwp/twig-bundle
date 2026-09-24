<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Usage
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Usage;

use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\LoaderPass;
use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\SafeClassPass;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Bundle\Attributes;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Bundle\TemplateFinder;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Usage\Mail\Newsletter;
use OffsetWP\Bundle\TwigBundle\TwigBundle;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * A project running a bundle written for another Twig integration, as it is.
 *
 * A bundle of that kind reaches Twig through the ids and the tags of the integration it
 * was written for: "twig" as a definition it can edit, "twig.loader" as a service it can
 * inject, "twig.safe_class" as a mark on a class of its own. This bundle answers to all
 * of them, and each case below is one — each of which used to fail without a word, on a
 * page that simply rendered wrong.
 */
#[CoversClass( TwigBundle::class )]
#[CoversClass( LoaderPass::class )]
#[CoversClass( SafeClassPass::class )]
final class ForeignBundleTest extends UsageTestCase {
	/**
	 * Its compiler pass edits the definition it finds under "twig", and what it put
	 * there reaches the environment.
	 *
	 * @return void
	 */
	public function testACompilerPassEditingTheTwigDefinitionIsHeard(): void {
		$this->assertSame( "reached\n", $this->twig( $this->boot( 'SiteWithAForeignBundle' ) )->render( 'foreign.twig' ) );
	}

	/**
	 * Its service injected with "twig.loader" holds the very loader the environment
	 * reads from, so it finds the templates Twig finds.
	 *
	 * @return void
	 */
	public function testAServiceInjectedWithTheTwigLoaderReadsWhereTwigReads(): void {
		$kernel = $this->boot( 'SiteWithAForeignBundle' );
		$finder = $kernel->service( 'foreign.template_finder' );

		$this->assertInstanceOf( TemplateFinder::class, $finder );
		$this->assertSame( $this->twig( $kernel )->getLoader(), $finder->loader() );
		$this->assertTrue( $finder->loader()->exists( 'foreign.twig' ) );
	}

	/**
	 * The class it marks safe is printed as it renders itself, not escaped a second time.
	 *
	 * @return void
	 */
	public function testAClassItMarksSafeIsPrintedAsItIs(): void {
		$this->assertSame(
			"<p class=\"alert\">\n",
			$this->twig( $this->boot( 'SiteWithAForeignBundle' ) )->render( 'attributes.twig', array( 'attributes' => new Attributes( array( 'class' => 'alert' ) ) ) )
		);
	}

	/**
	 * And next to it, a service of the project's own asking for Twig by its class gets
	 * the environment everything else renders with — not a second one.
	 *
	 * @return void
	 */
	public function testAServiceAutowiredOnTheEnvironmentClassGetsTheSameEnvironment(): void {
		$kernel     = $this->boot( 'SiteWithAForeignBundle' );
		$newsletter = $kernel->service( Newsletter::class );

		$this->assertInstanceOf( Newsletter::class, $newsletter );
		$this->assertSame( $this->twig( $kernel ), $newsletter->environment() );
		$this->assertSame( twig(), $newsletter->environment() );
	}
}
