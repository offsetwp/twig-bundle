<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Integration
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Integration;

use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\CountedExtension;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\StaticCountedExtension;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\KernelTestCase;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\TestKernel;
use OffsetWP\Bundle\TwigBundle\Twig;
use OffsetWP\Bundle\TwigBundle\TwigBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Environment;

/**
 * Nothing unused is ever instantiated.
 *
 * This is the guarantee the rest of the package is arranged around, so it is a
 * counter and a set of assertions rather than a claim in a docblock.
 */
#[CoversClass( TwigBundle::class )]
#[CoversClass( Twig::class )]
final class LazinessTest extends KernelTestCase {
	/**
	 * The counters are static, and the suite runs in one process and in random order.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		CountedExtension::$constructions       = 0;
		StaticCountedExtension::$constructions = 0;
	}

	/**
	 * Boot a kernel with both counted fixtures registered, and nothing else.
	 *
	 * @return TestKernel
	 */
	private function bootCounted(): TestKernel {
		return $this->boot(
			array(),
			static function ( ContainerBuilder $container ): void {
				foreach ( array( CountedExtension::class, StaticCountedExtension::class ) as $counted ) {
					$container->register( $counted, $counted )
						->setAutowired( true )
						->setAutoconfigured( true );
				}
			}
		);
	}

	/**
	 * Booting builds the container and stops there. The environment itself is not
	 * constructed, which is what keeps a request that renders nothing free.
	 *
	 * @return void
	 */
	public function testBootingTheKernelConstructsNothing(): void {
		$container = $this->containerOf( $this->bootCounted() );

		$this->assertFalse( $container->initialized( Environment::class ) );
		$this->assertSame( 0, CountedExtension::$constructions );
		$this->assertSame( 0, StaticCountedExtension::$constructions );
	}

	/**
	 * Building the environment does not build the extensions behind the attributes.
	 * The wrapper Twig ships holds a class name, and the class sits in a service
	 * locator: referenced, so never removed, and never constructed.
	 *
	 * @return void
	 */
	public function testAnUnusedAttributedClassIsNeverConstructed(): void {
		$twig = $this->twig( $this->bootCounted() );

		$this->assertSame( "plain\n", $twig->render( 'laziness/no-filter.twig' ) );
		$this->assertSame( 0, CountedExtension::$constructions );
	}

	/**
	 * And the other half: a template that does use the filter constructs the class,
	 * once, however many times it renders afterwards.
	 *
	 * The three readings are taken first and compared once. Asserted one by one, each
	 * assertion pinned the counter for the static analyser, which then carried the
	 * pinned value across the render that changes it and reported the next assertion
	 * as settled in advance.
	 *
	 * @return void
	 */
	public function testAUsedAttributedClassIsConstructedExactlyOnce(): void {
		$twig = $this->twig( $this->bootCounted() );

		$constructions = array( CountedExtension::$constructions );

		$twig->render( 'laziness/uses-filter.twig' );

		$constructions[] = CountedExtension::$constructions;

		$twig->render( 'laziness/uses-filter.twig' );

		$constructions[] = CountedExtension::$constructions;

		$this->assertSame( array( 0, 1, 1 ), $constructions );
	}

	/**
	 * A static attributed method compiles to a direct call, so its class is never
	 * constructed at all — not on boot, not on the first use, not ever.
	 *
	 * @return void
	 */
	public function testAStaticAttributedMethodConstructsNothing(): void {
		$twig = $this->twig( $this->bootCounted() );

		$this->assertSame( 'yes', $twig->render( 'laziness/uses-static.twig' ) );
		$this->assertSame( 0, StaticCountedExtension::$constructions );
	}

	/**
	 * What the bundle does on boot is hand the container over — not the environment,
	 * which does not exist yet and will not until somebody asks. The facade is ready
	 * and the environment is still unbuilt.
	 *
	 * @return void
	 */
	public function testBootingRegistersTheContainerWithoutBuildingTheEnvironment(): void {
		$kernel = $this->bootCounted();

		$this->assertTrue( Twig::booted() );
		$this->assertFalse( $this->containerOf( $kernel )->initialized( Environment::class ) );
		$this->assertSame( $this->twig( $kernel ), Twig::environment() );
	}
}
