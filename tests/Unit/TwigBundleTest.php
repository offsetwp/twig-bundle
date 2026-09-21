<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Unit
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Unit;

use OffsetWP\Bundle\TwigBundle\TwigBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * TwigBundle
 */
#[CoversClass( TwigBundle::class )]
final class TwigBundleTest extends TestCase {
	/**
	 * The bundle name is the short class name. It is what a host writes in
	 * config/bundles.php, and what the extension alias is derived from.
	 *
	 * @return void
	 */
	public function testTheBundleNameIsDerivedFromTheClassName(): void {
		$this->assertSame( 'TwigBundle', ( new TwigBundle() )->getName() );
	}

	/**
	 * The configuration root key. A host writes config/packages/twig.php, and a
	 * sibling bundle prepends into "twig"; both depend on this alias staying put.
	 *
	 * @return void
	 */
	public function testTheContainerExtensionAliasIsTwig(): void {
		$extension = ( new TwigBundle() )->getContainerExtension();

		$this->assertNotNull( $extension, 'The bundle must expose a container extension.' );
		$this->assertSame( 'twig', $extension->getAlias() );
	}

	/**
	 * A bundle only ever receives a container from the kernel that registered it, so
	 * one booted by hand is a mistake worth a sentence rather than a PHP fatal about
	 * an uninitialised property.
	 *
	 * @return void
	 */
	public function testBootingTheBundleWithoutAContainerIsRefused(): void {
		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage(
			'Cannot boot the Twig bundle without a container. A bundle receives one from the kernel that registered it, so boot the kernel rather than the bundle.'
		);

		( new TwigBundle() )->boot();
	}
}
