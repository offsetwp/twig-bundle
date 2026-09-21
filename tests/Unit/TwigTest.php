<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Unit
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Unit;

use OffsetWP\Bundle\TwigBundle\Twig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The facade and its registry, against bare containers: no kernel, no filesystem.
 */
#[CoversClass( Twig::class )]
final class TwigTest extends TestCase {
	/**
	 * The registry is static, and the suite runs in one process and in random order.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Twig::reset();
	}

	/**
	 * Leave nothing behind for the integration suite to trip over.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Twig::reset();

		parent::tearDown();
	}

	/**
	 * A container holding one environment under the short id.
	 *
	 * @param string $template The single template that environment can render.
	 * @return ContainerBuilder
	 */
	private function container( string $template = 'hello' ): ContainerBuilder {
		$container = new ContainerBuilder();
		$container->set(
			'twig',
			new Environment(
				new ArrayLoader(
					array(
						'greeting' => $template,
						'named'    => '{{ name }}',
					)
				)
			)
		);

		return $container;
	}

	/**
	 * Registering is what a booting kernel does, and it is all it does.
	 *
	 * @return void
	 */
	public function testRegisteringAContainerMakesTheFacadeReady(): void {
		$this->assertFalse( Twig::booted() );

		Twig::register( $this->container() );

		$this->assertTrue( Twig::booted() );
		$this->assertSame( 'hello', Twig::render( 'greeting' ) );
	}

	/**
	 * The shortcut designates the last kernel to boot. In this platform the
	 * theme boots after the mu-plugins, and the theme is what a developer is editing
	 * when they reach for the shortcut.
	 *
	 * @return void
	 */
	public function testTheLastRegisteredContainerWins(): void {
		Twig::register( $this->container( 'from the first' ) );
		Twig::register( $this->container( 'from the second' ) );

		$this->assertSame( 'from the second', Twig::render( 'greeting' ) );
	}

	/**
	 * Reset exists so that a test can start from nothing. Production code never calls
	 * it.
	 *
	 * @return void
	 */
	public function testResetClearsTheRegistry(): void {
		Twig::register( $this->container() );
		Twig::reset();

		$this->assertFalse( Twig::booted() );
	}

	/**
	 * Asking whether the shortcut would work has to be answerable without risking the
	 * failure the question is about.
	 *
	 * @return void
	 */
	public function testBootedIsFalseBeforeAnyRegistrationAndDoesNotThrow(): void {
		$this->assertFalse( Twig::booted() );
	}

	/**
	 * A kernel can boot without this bundle in its config/bundles.php. It registers
	 * nothing here, but any kernel booting after it does — so the registry holds a
	 * container, and the question "would the shortcut work" was answered yes on the
	 * strength of a container having been registered at all.
	 *
	 * @return void
	 */
	public function testBootedIsFalseWhenTheRegisteredContainerHasNoTwigService(): void {
		Twig::register( new ContainerBuilder() );

		$this->assertFalse( Twig::booted() );
	}

	/**
	 * The container branch of Twig::of() names the kernel too, when the container is
	 * one: the root path is a parameter every kernel publishes. Only a container from
	 * somewhere else has nothing to name.
	 *
	 * @return void
	 */
	public function testAContainerThatKnowsItsRootPathNamesTheKernel(): void {
		$container = new ContainerBuilder();
		$container->setParameter( 'kernel.root_path', '/srv/site' );

		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage(
			'The kernel rooted at "/srv/site" has no "twig" service. Add OffsetWP\\Bundle\\TwigBundle\\TwigBundle to its config/bundles.php.'
		);

		Twig::of( $container );
	}

	/**
	 * And a foreign service names it as well. In the request this facade exists for —
	 * a mu-plugin and a theme each booting a kernel — which of the two defined the
	 * service is the only thing the reader needs.
	 *
	 * @return void
	 */
	public function testAForeignTwigServiceNamesTheKernelWhenThereIsOneToName(): void {
		$container = new ContainerBuilder();
		$container->setParameter( 'kernel.root_path', '/srv/site' );
		$container->set( 'twig', new \stdClass() );

		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'The kernel is rooted at "/srv/site".' );

		Twig::of( $container );
	}

	/**
	 * This class is named Twig and lives in the bundle namespace, so a partially
	 * qualified Twig\Environment written anywhere inside that namespace resolves to
	 * OffsetWP\Bundle\TwigBundle\Twig\Environment — a class under this very facade,
	 * which does not exist.
	 *
	 * The first assertion says the facade hands back the real one. The second says the
	 * name a careless reference would resolve to is not a class at all, so the mistake
	 * can only ever be a loud failure and never a quiet wrong answer.
	 *
	 * @return void
	 */
	public function testTheFacadeResolvesTheRealTwigEnvironmentClass(): void {
		Twig::register( $this->container() );

		$environment = Twig::environment();

		$this->assertSame( 'Twig\Environment', $environment::class );
		$this->assertFalse( class_exists( 'OffsetWP\Bundle\TwigBundle\Twig\Environment' ) );
	}

	/**
	 * The third shape of the entry point, and the one a theme file uses when it has
	 * nothing to do with the string afterwards.
	 *
	 * @return void
	 */
	public function testDisplayWritesTheRenderedTemplateToTheOutput(): void {
		Twig::register( $this->container() );

		$this->expectOutputString( 'hello' );

		Twig::display( 'greeting' );
	}

	/**
	 * And it passes a context through, which render() was tested for and display()
	 * was not — the two are separate delegations and either could have dropped it.
	 *
	 * @return void
	 */
	public function testDisplayPassesItsContextThrough(): void {
		Twig::register( $this->container() );

		$this->expectOutputString( 'Jérôme' );

		Twig::display( 'named', array( 'name' => 'Jérôme' ) );
	}

	/**
	 * A project that defined its own service under the id "twig" gets told so, rather
	 * than a type error from wherever the value was finally used.
	 *
	 * @return void
	 */
	public function testAForeignTwigServiceIsRefused(): void {
		$container = new ContainerBuilder();
		$container->set( 'twig', new \stdClass() );

		Twig::register( $container );

		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage(
			'The "twig" service is a "stdClass", not a "Twig\\Environment". Something else in this project has defined a service under that id.'
		);

		Twig::environment();
	}

	/**
	 * Twig::of() takes a bare container too, and one that never heard of this bundle
	 * has no root path to name — so the message names what to add instead.
	 *
	 * @return void
	 */
	public function testAContainerWithoutTheBundleNamesWhatToAdd(): void {
		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage(
			'This container has no "twig" service. Add OffsetWP\\Bundle\\TwigBundle\\TwigBundle to the config/bundles.php of its kernel.'
		);

		Twig::of( new ContainerBuilder() );
	}
}
