<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Unit\DependencyInjection\Compiler
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Unit\DependencyInjection\Compiler;

use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\SafeClassPass;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Markup\HtmlSnippet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Runtime\EscaperRuntime;

/**
 * The pass that tells Twig's escaper which classes print markup of their own.
 */
#[CoversClass( SafeClassPass::class )]
final class SafeClassPassTest extends TestCase {
	/**
	 * A container holding nothing but the escaper the marked classes are added to.
	 *
	 * @return ContainerBuilder
	 */
	private function container(): ContainerBuilder {
		$container = new ContainerBuilder();
		$container->register( SafeClassPass::ESCAPER_ID, EscaperRuntime::class )->setArguments( array( 'UTF-8' ) );

		return $container;
	}

	/**
	 * The addSafeClass() calls the pass put on the escaper, each as its two arguments.
	 *
	 * @param ContainerBuilder $container The processed container.
	 * @return list<array<array-key, mixed>>
	 */
	private function safeClasses( ContainerBuilder $container ): array {
		$found = array();

		foreach ( $container->getDefinition( SafeClassPass::ESCAPER_ID )->getMethodCalls() as $call ) {
			if ( ! is_array( $call ) || 'addSafeClass' !== ( $call[0] ?? null ) ) {
				continue;
			}

			$found[] = is_array( $call[1] ?? null ) ? $call[1] : array();
		}

		return $found;
	}

	/**
	 * Every shape a valid strategy takes, as written and as Twig's escaper receives it.
	 *
	 * @return array<string, array{mixed, list<string>}>
	 */
	public static function validStrategies(): array {
		return array(
			'one name'                 => array( 'html', array( 'html' ) ),
			'a list of names'          => array( array( 'html', 'js' ), array( 'html', 'js' ) ),
			'every strategy at once'   => array( 'all', array( 'all' ) ),
			'a strategy of your own'   => array( 'markdown_2', array( 'markdown_2' ) ),
			'a list written with keys' => array(
				array(
					'body'  => 'html',
					'style' => 'css',
				),
				array( 'html', 'css' ),
			),
			'every name Twig ships'    => array(
				array( 'html', 'js', 'css', 'url', 'html_attr', 'html_attr_relaxed' ),
				array( 'html', 'js', 'css', 'url', 'html_attr', 'html_attr_relaxed' ),
			),
		);
	}

	/**
	 * A marked class reaches the escaper under its own class name, with its strategies
	 * as a plain list — a single name becomes a list of one.
	 *
	 * @param mixed              $strategy The strategy attribute, as a host writes it.
	 * @param array<int, string> $expected The strategies the escaper receives.
	 * @return void
	 */
	#[DataProvider( 'validStrategies' )]
	public function testAMarkedClassReachesTheEscaper( mixed $strategy, array $expected ): void {
		$container = $this->container();
		$container->register( 'app.snippet', HtmlSnippet::class )
			->addResourceTag( SafeClassPass::SAFE_CLASS_TAG, array( 'strategy' => $strategy ) );

		( new SafeClassPass() )->process( $container );

		$this->assertSame( array( array( HtmlSnippet::class, $expected ) ), $this->safeClasses( $container ) );
	}

	/**
	 * A class named by a container parameter reaches the escaper under the class it
	 * names, not under the expression.
	 *
	 * @return void
	 */
	public function testAClassNamedByAParameterIsResolved(): void {
		$container = $this->container();
		$container->setParameter( 'app.snippet_class', HtmlSnippet::class );
		$container->register( 'app.snippet', '%app.snippet_class%' )
			->addResourceTag( SafeClassPass::SAFE_CLASS_TAG, array( 'strategy' => 'html' ) );

		( new SafeClassPass() )->process( $container );

		$this->assertSame( array( array( HtmlSnippet::class, array( 'html' ) ) ), $this->safeClasses( $container ) );
	}

	/**
	 * Each occurrence of the tag is handed over as it was written. Twig merges the
	 * strategies of one class itself.
	 *
	 * @return void
	 */
	public function testEachOccurrenceOfTheTagIsHandedOver(): void {
		$container = $this->container();
		$container->register( 'app.snippet', HtmlSnippet::class )
			->addResourceTag( SafeClassPass::SAFE_CLASS_TAG, array( 'strategy' => 'html' ) )
			->addResourceTag( SafeClassPass::SAFE_CLASS_TAG, array( 'strategy' => 'js' ) );

		( new SafeClassPass() )->process( $container );

		$this->assertSame(
			array(
				array( HtmlSnippet::class, array( 'html' ) ),
				array( HtmlSnippet::class, array( 'js' ) ),
			),
			$this->safeClasses( $container )
		);
	}

	/**
	 * Every way a strategy can be wrong, and the part of the message that says which.
	 *
	 * A trailing newline is one of them: a pattern anchored with a plain dollar lets it
	 * through, and Twig would then never be asked for a strategy of that name.
	 *
	 * @return array<string, array{array<string, mixed>, string}>
	 */
	public static function invalidStrategies(): array {
		return array(
			'missing'                => array( array(), 'without a "strategy" attribute' ),
			'an empty list'          => array( array( 'strategy' => array() ), 'with an empty "strategy" list' ),
			'neither name nor list'  => array( array( 'strategy' => 1 ), 'with a "strategy" of type int' ),
			'a list holding a bool'  => array( array( 'strategy' => array( 'html', true ) ), 'with a "strategy" list holding bool' ),
			'an empty name'          => array( array( 'strategy' => '' ), 'with the strategy ""' ),
			'upper case'             => array( array( 'strategy' => 'HTML' ), 'with the strategy "HTML"' ),
			'a hyphen'               => array( array( 'strategy' => 'html-attr' ), 'with the strategy "html-attr"' ),
			'a leading digit'        => array( array( 'strategy' => '2html' ), 'with the strategy "2html"' ),
			'a trailing newline'     => array( array( 'strategy' => "html\n" ), "with the strategy \"html\n\"" ),
			'one bad name in a list' => array( array( 'strategy' => array( 'html', 'Js' ) ), 'with the strategy "Js"' ),
		);
	}

	/**
	 * A strategy that is missing, empty or malformed is refused, naming the service
	 * that carries the tag.
	 *
	 * @param array<string, mixed> $attributes The tag attributes, as a host writes them.
	 * @param string               $expected   What the message says of them.
	 * @return void
	 */
	#[DataProvider( 'invalidStrategies' )]
	public function testAnInvalidStrategyIsRefusedAndNamesTheService( array $attributes, string $expected ): void {
		$container = $this->container();
		$container->register( 'app.snippet', HtmlSnippet::class )
			->addResourceTag( SafeClassPass::SAFE_CLASS_TAG, $attributes );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( sprintf( 'The service "app.snippet" is tagged "twig.safe_class" %s', $expected ) );

		( new SafeClassPass() )->process( $container );
	}

	/**
	 * The mark written as an ordinary tag rather than a resource tag. The library
	 * refuses it as the tag is collected, naming the service — which is why the pass
	 * holds no check of its own for it.
	 *
	 * @return void
	 */
	public function testAMarkWrittenAsAnOrdinaryTagIsRefused(): void {
		$container = $this->container();
		$container->register( 'app.snippet', HtmlSnippet::class )
			->addTag( SafeClassPass::SAFE_CLASS_TAG, array( 'strategy' => 'html' ) );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'The resource "app.snippet" tagged "twig.safe_class" is missing the "container.excluded" tag' );

		( new SafeClassPass() )->process( $container );
	}

	/**
	 * A container compiled without this bundle's services has no escaper to add to, and
	 * the pass leaves it alone — even a mark it would have refused.
	 *
	 * @return void
	 */
	public function testItDoesNothingWhenThereIsNoEscaperToConfigure(): void {
		$container = new ContainerBuilder();
		$container->register( 'app.snippet', HtmlSnippet::class )
			->addTag( SafeClassPass::SAFE_CLASS_TAG, array( 'strategy' => 'html' ) );

		( new SafeClassPass() )->process( $container );

		$this->assertFalse( $container->hasDefinition( SafeClassPass::ESCAPER_ID ) );
	}
}
