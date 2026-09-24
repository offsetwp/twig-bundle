<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Unit\DependencyInjection\Compiler
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Unit\DependencyInjection\Compiler;

use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\ExtensionPass;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\GreetingExtension;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\ShopExtension;
use OffsetWP\Bundle\TwigBundle\TwigBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Extension\StringLoaderExtension;

/**
 * The pass that collects tagged extensions, against a bare container builder: no
 * kernel, no configuration, no filesystem.
 */
#[CoversClass( ExtensionPass::class )]
final class ExtensionPassTest extends TestCase {
	/**
	 * A container holding nothing but an environment definition to append to.
	 *
	 * @return ContainerBuilder
	 */
	private function container(): ContainerBuilder {
		$container = new ContainerBuilder();
		$container->register( TwigBundle::ENVIRONMENT_ID, Environment::class );

		return $container;
	}

	/**
	 * The service ids the pass appended to the environment, in order.
	 *
	 * @param ContainerBuilder $container The processed container.
	 * @return array<int, string>
	 */
	private function addedExtensions( ContainerBuilder $container ): array {
		$ids = array();

		foreach ( $container->getDefinition( TwigBundle::ENVIRONMENT_ID )->getMethodCalls() as $call ) {
			if ( ! is_array( $call ) || 'addExtension' !== ( $call[0] ?? null ) ) {
				continue;
			}

			$argument = is_array( $call[1] ?? null ) ? ( $call[1][0] ?? null ) : null;

			if ( $argument instanceof Reference ) {
				$ids[] = (string) $argument;
			}
		}

		return $ids;
	}

	/**
	 * One call per tagged service, and nothing constructed: the pass appends method
	 * calls, it does not build extensions.
	 *
	 * @return void
	 */
	public function testItAppendsOneAddExtensionCallPerTaggedService(): void {
		$container = $this->container();
		$container->register( 'shop', ShopExtension::class )->addTag( ExtensionPass::EXTENSION_TAG );
		$container->register( 'greeting', GreetingExtension::class )->addTag( ExtensionPass::EXTENSION_TAG );

		( new ExtensionPass() )->process( $container );

		$this->assertSame( array( 'shop', 'greeting' ), $this->addedExtensions( $container ) );
	}

	/**
	 * Higher priority first, and a tie keeps the order the host declared. Two pairs
	 * are registered interleaved so that neither rule can pass by accident.
	 *
	 * @return void
	 */
	public function testItOrdersByPriorityThenByDeclaration(): void {
		$container = $this->container();

		$services = array(
			'a' => array( ShopExtension::class, 0 ),
			'b' => array( GreetingExtension::class, 10 ),
			'c' => array( DebugExtension::class, 0 ),
			'd' => array( StringLoaderExtension::class, 10 ),
		);

		foreach ( $services as $id => $service ) {
			$container->register( $id, $service[0] )
				->addTag( ExtensionPass::EXTENSION_TAG, array( 'priority' => $service[1] ) );
		}

		( new ExtensionPass() )->process( $container );

		$this->assertSame( array( 'b', 'd', 'a', 'c' ), $this->addedExtensions( $container ) );
	}

	/**
	 * A project using no extension at all pays nothing for this pass.
	 *
	 * @return void
	 */
	public function testItDoesNothingWhenNoServiceIsTagged(): void {
		$container = $this->container();

		( new ExtensionPass() )->process( $container );

		$this->assertSame( array(), $container->getDefinition( TwigBundle::ENVIRONMENT_ID )->getMethodCalls() );
	}

	/**
	 * The arrangement the README documents: autoconfiguration on, and the tag written
	 * by hand to set a priority. The container keeps both occurrences, because it only
	 * skips an autoconfigured tag whose attributes are identical to one already there,
	 * and an attribute-less tag is not identical to a priority. Counted twice, the
	 * service was refused as a duplicate of itself, in a message naming it as both
	 * halves of the collision.
	 *
	 * The priority of the hand-written one survives, which is why "greeting" comes
	 * second here.
	 *
	 * @return void
	 */
	public function testAServiceCarryingTheTagTwiceIsCountedOnce(): void {
		$container = $this->container();
		$container->register( 'shop', ShopExtension::class )
			->addTag( ExtensionPass::EXTENSION_TAG, array( 'priority' => 10 ) )
			->addTag( ExtensionPass::EXTENSION_TAG );
		$container->register( 'greeting', GreetingExtension::class )
			->addTag( ExtensionPass::EXTENSION_TAG, array( 'priority' => 5 ) );

		( new ExtensionPass() )->process( $container );

		$this->assertSame( array( 'shop', 'greeting' ), $this->addedExtensions( $container ) );
	}

	/**
	 * A class named through a container parameter. The parameter is resolved by a pass
	 * that runs after this one, so read raw the class is "%app.extension_class%",
	 * which implements nothing — and the extension was refused for it, in a message
	 * naming the expression as the class at fault.
	 *
	 * @return void
	 */
	public function testAClassNamedThroughAParameterIsResolvedBeforeItIsChecked(): void {
		$container = $this->container();
		$container->setParameter( 'app.extension_class', ShopExtension::class );
		$container->register( 'shop', '%app.extension_class%' )->addTag( ExtensionPass::EXTENSION_TAG );

		( new ExtensionPass() )->process( $container );

		$this->assertSame( array( 'shop' ), $this->addedExtensions( $container ) );
	}

	/**
	 * The attribute tag carries a class name and nothing else — it is the whole
	 * payload, since Twig reads the attributes off the class itself. A service with no
	 * class of its own used to produce a wrapper around an empty string, which fails
	 * at the first template compile on "Class \"\" does not exist".
	 *
	 * @return void
	 */
	public function testAnAttributedServiceWithNoClassIsRefused(): void {
		$container = $this->container();
		$container->register( 'app.built_by_a_factory' )->addTag( ExtensionPass::ATTRIBUTE_EXTENSION_TAG );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage(
			'The service "app.built_by_a_factory" is tagged "twig.attribute_extension" but has no class of its own.'
		);

		( new ExtensionPass() )->process( $container );
	}

	/**
	 * Zero is a real priority — it is the one the filesystem loader carries — so
	 * reading a typo as zero means an extension that was meant to come first quietly
	 * comes last, and nothing says the value was not understood.
	 *
	 * @return void
	 */
	public function testAPriorityThatIsNotAWholeNumberIsRefused(): void {
		$container = $this->container();
		$container->register( 'shop', ShopExtension::class )
			->addTag( ExtensionPass::EXTENSION_TAG, array( 'priority' => 'high' ) );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage(
			'The service "shop" is tagged "twig.extension" with a priority of string. A priority is a whole number, and higher reaches Twig first.'
		);

		( new ExtensionPass() )->process( $container );
	}

	/**
	 * An abstract definition cannot be instantiated, so it cannot be referenced
	 * either. Left to itself the failure named the environment — the definition
	 * holding the reference — rather than the service carrying the tag.
	 *
	 * @return void
	 */
	public function testAnAbstractServiceCarryingTheTagIsRefused(): void {
		$container = $this->container();
		$container->register( 'shop', ShopExtension::class )
			->setAbstract( true )
			->addTag( ExtensionPass::EXTENSION_TAG );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'The service "shop" tagged "twig.extension" must not be abstract.' );

		( new ExtensionPass() )->process( $container );
	}

	/**
	 * A container compiled without this bundle still runs its passes, so every one of
	 * them begins by asking whether there is an environment to work on.
	 *
	 * @return void
	 */
	public function testItDoesNothingWhenThereIsNoEnvironmentToConfigure(): void {
		$container = new ContainerBuilder();
		$container->register( 'shop', ShopExtension::class )->addTag( ExtensionPass::EXTENSION_TAG );

		( new ExtensionPass() )->process( $container );

		$this->assertFalse( $container->hasDefinition( TwigBundle::ENVIRONMENT_ID ) );
	}

	/**
	 * Twig refuses a duplicate extension at runtime with a message naming neither
	 * source, which is a long evening for whoever has to find the two registrations
	 * that collided. This names both.
	 *
	 * @return void
	 */
	public function testTheSameExtensionClassFromTwoSourcesIsRefused(): void {
		$container = $this->container();
		$container->register( 'app.shop', ShopExtension::class )->addTag( ExtensionPass::EXTENSION_TAG );
		$container->register( ShopExtension::class, ShopExtension::class )
			->addTag( ExtensionPass::EXTENSION_TAG, array( 'source' => 'twig.extensions' ) );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage(
			sprintf(
				'The Twig extension "%s" is registered twice: by the service "app.shop" and by the "twig.extensions" config key.',
				ShopExtension::class
			)
		);

		( new ExtensionPass() )->process( $container );
	}
}
