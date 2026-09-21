<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Integration
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Integration;

use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\KernelTestCase;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\TestKernel;
use OffsetWP\Bundle\TwigBundle\TwigBundle;
use OffsetWP\Bundle\TwigBundle\Twig;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * More than one kernel in one request, which is the ordinary arrangement here: a
 * mu-plugin boots one and a theme boots another.
 */
#[CoversClass( Twig::class )]
#[CoversClass( TwigBundle::class )]
final class MultiKernelTest extends KernelTestCase {
	/**
	 * Boot two kernels on two projects, in that order, the way a mu-plugin and then a
	 * theme would.
	 *
	 * @return array{TestKernel, TestKernel}
	 */
	private function bootBoth(): array {
		$first  = $this->boot();
		$second = $this->boot( array(), null, 'SecondProject' );

		return array( $first, $second );
	}

	/**
	 * The unambiguous form. Whatever the shortcut points at, naming a kernel gives
	 * that kernel's environment and nothing else.
	 *
	 * @return void
	 */
	public function testOfResolvesTheEnvironmentOfASpecificKernel(): void {
		$kernel = $this->boot();

		$this->assertSame( $this->twig( $kernel ), Twig::of( $kernel ) );
		$this->assertSame( $this->twig( $kernel ), Twig::of( $kernel->container() ) );
	}

	/**
	 * Each kernel compiles its own container, so each owns its own environment and
	 * they share nothing — not even a compiled template, although both projects call
	 * theirs hello.twig.
	 *
	 * That a second kernel can boot at all is the failure mode that ruled out the
	 * framework's own instance registry: it refuses a second registration under the
	 * same name, which would turn the ordinary mu-plugin-and-theme arrangement into a
	 * fatal. Two distinct containers is what says it did not.
	 *
	 * @return void
	 */
	public function testTwoKernelsOwnTwoDistinctEnvironments(): void {
		list( $first, $second ) = $this->bootBoth();

		$context = array( 'name' => 'Jérôme' );

		$this->assertNotSame( $first->container(), $second->container() );
		$this->assertNotSame( $this->twig( $first ), $this->twig( $second ) );
		$this->assertSame( "Hello, Jérôme!\n", $this->twig( $first )->render( 'hello.twig', $context ) );
		$this->assertSame( "Bonjour, Jérôme !\n", $this->twig( $second )->render( 'hello.twig', $context ) );
	}

	/**
	 * The shortcut designates the last kernel to boot. Here the theme boots
	 * after the mu-plugins, and the theme is what a developer is editing when they
	 * type twig().
	 *
	 * @return void
	 */
	public function testTheGlobalShortcutResolvesTheMostRecentlyBootedKernel(): void {
		list( $first, $second ) = $this->bootBoth();

		$this->assertSame( $this->twig( $second ), Twig::environment() );
		$this->assertSame( $this->twig( $first ), Twig::of( $first ) );
		$this->assertSame( "Bonjour, Jérôme !\n", twig( 'hello.twig', array( 'name' => 'Jérôme' ) ) );
	}
}
