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
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The global function, which is what a theme file actually types.
 */
#[CoversFunction( 'twig' )]
final class HelpersTest extends TestCase {
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
	 * Leave nothing behind for the next test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Twig::reset();

		parent::tearDown();
	}

	/**
	 * Register a container holding one environment that can render one template.
	 *
	 * @return Environment
	 */
	private function register(): Environment {
		$environment = new Environment( new ArrayLoader( array( 'greeting' => 'Hello, {{ name }}!' ) ) );

		$container = new ContainerBuilder();
		$container->set( 'twig', $environment );

		Twig::register( $container );

		return $environment;
	}

	/**
	 * Called with nothing, it hands back the real environment — every method Twig
	 * documents, wrapped in nothing.
	 *
	 * @return void
	 */
	public function testTwigWithoutArgumentsReturnsTheEnvironment(): void {
		$this->assertSame( $this->register(), twig() );
	}

	/**
	 * Called with a name, it renders. This is the one-token call a theme file makes.
	 *
	 * @return void
	 */
	public function testTwigWithATemplateNameReturnsTheRenderedString(): void {
		$this->register();

		$this->assertSame( 'Hello, Jérôme!', twig( 'greeting', array( 'name' => 'Jérôme' ) ) );
	}

	/**
	 * Called with no template name the helper hands back the environment, and there is
	 * nothing to render, so a context passed alongside is ignored rather than being an
	 * error. The signature allows it — a default argument cannot be forbidden by one
	 * branch — so the behaviour is worth pinning rather than leaving to be discovered.
	 *
	 * @return void
	 */
	public function testAContextPassedWithNoTemplateNameIsIgnored(): void {
		$environment = $this->register();

		$this->assertSame( $environment, twig( null, array( 'name' => 'Jérôme' ) ) );
	}

	/**
	 * The file is listed under Composer's "files" autoload, so it is loaded before
	 * anything else and can be loaded again — by a host that requires it by hand, or
	 * by a second copy of this package in a tree. Loading it again has to leave the
	 * function that is already there alone.
	 *
	 * Both assertions used to be function_exists(), which is true from the first line
	 * of the suite and says nothing about what the second require did. This compares
	 * the file the function came from before and after, which is the only observable
	 * difference a redefinition could make — and a redefinition that got past the
	 * guard would not reach either assertion, since "Cannot redeclare twig()" is a
	 * fatal that ends the process.
	 *
	 * @return void
	 */
	public function testRequiringTheHelpersFileAgainLeavesTheFunctionAlone(): void {
		$before = ( new \ReflectionFunction( 'twig' ) )->getFileName();

		require dirname( __DIR__, 2 ) . '/src/helpers.php';

		$this->assertSame( $before, ( new \ReflectionFunction( 'twig' ) )->getFileName() );
		$this->assertStringEndsWith( 'src/helpers.php', (string) $before );
	}

	/**
	 * Two ways of typing one thing. The helper is three lines of delegation and has to
	 * stay that way, so the two are asserted to agree rather than trusted to.
	 *
	 * @return void
	 */
	public function testTheHelperAndTheFacadeReturnIdenticalOutput(): void {
		$this->register();

		$context = array( 'name' => 'Jérôme' );

		$this->assertSame( Twig::render( 'greeting', $context ), twig( 'greeting', $context ) );
		$this->assertSame( Twig::environment(), twig() );
	}
}
