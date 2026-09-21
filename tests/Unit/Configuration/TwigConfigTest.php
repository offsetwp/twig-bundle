<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Unit\Configuration
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Unit\Configuration;

use OffsetWP\Bundle\TwigBundle\Configuration\Escaping;
use OffsetWP\Bundle\TwigBundle\Configuration\TwigConfig;
use OffsetWP\Bundle\TwigBundle\TwigBundle;
use OffsetWP\Framework\Bundle\BundleExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Extension\DebugExtension;

/**
 * The configuration written as method calls.
 *
 * What matters about this object is not that it builds an array — it is that the array
 * it builds means the same thing as the one somebody would have typed, and that it can
 * still say everything the tree accepts. Both are asserted here rather than reviewed.
 */
#[CoversClass( TwigConfig::class )]
final class TwigConfigTest extends TestCase {
	/**
	 * Run a configuration through the tree, the way the container does.
	 *
	 * @param array<string, mixed> $config The configuration.
	 * @return array<array-key, mixed>
	 */
	private function process( array $config ): array {
		$extension = ( new TwigBundle() )->getContainerExtension();

		$this->assertInstanceOf( BundleExtension::class, $extension );

		$configuration = $extension->getConfiguration( array(), new ContainerBuilder() );

		$this->assertNotNull( $configuration );

		return ( new Processor() )->processConfiguration( $configuration, array( $config ) );
	}

	/**
	 * The tree's own keys, read off the tree.
	 *
	 * @param string|null $section A section to read the children of, or null for the root.
	 * @return array<int, string>
	 */
	private function treeKeys( ?string $section = null ): array {
		$extension = ( new TwigBundle() )->getContainerExtension();

		$this->assertInstanceOf( BundleExtension::class, $extension );

		$configuration = $extension->getConfiguration( array(), new ContainerBuilder() );

		$this->assertNotNull( $configuration );

		$node = $configuration->getConfigTreeBuilder()->buildTree();

		$this->assertInstanceOf( ArrayNode::class, $node );

		if ( null !== $section ) {
			$node = $node->getChildren()[ $section ];

			$this->assertInstanceOf( ArrayNode::class, $node );
		}

		return array_keys( $node->getChildren() );
	}

	/**
	 * A configuration touching every key the tree knows.
	 *
	 * @return TwigConfig
	 */
	private function everything(): TwigConfig {
		return TwigConfig::create()
			->path( '/abs/emails', 'emails' )
			->defaultPath( '/abs/templates' )
			->debug( true )
			->charset( 'UTF-8' )
			->strictVariables( true )
			->autoescape( Escaping::ByTemplateName )
			->cacheDirectory( '/abs/var/cache' )
			->autoReload( false )
			->optimizations( 0 )
			->useYield( true )
			->global( 'year', 2026 )
			->extension( DebugExtension::class )
			->dateFormat( 'd/m/Y' )
			->dateIntervalFormat( '%d days' )
			->dateTimezone( 'Europe/Paris' )
			->numberDecimals( 2 )
			->numberDecimalPoint( ',' )
			->numberThousandsSeparator( ' ' );
	}

	/**
	 * Nothing called, nothing written. The tree owns the defaults, and a builder that
	 * carried its own copy of them would be a second place for them to drift from.
	 *
	 * @return void
	 */
	public function testAnUntouchedConfigurationWritesNothing(): void {
		$this->assertSame( array(), TwigConfig::create()->toArray() );
	}

	/**
	 * And what it writes when untouched is what the tree would have produced from an
	 * empty array — which is the same thing said from the other end.
	 *
	 * @return void
	 */
	public function testAnUntouchedConfigurationProcessesToTheDocumentedDefaults(): void {
		$this->assertSame( $this->process( array() ), $this->process( TwigConfig::create()->toArray() ) );
	}

	/**
	 * Every key of the tree is reachable from this object.
	 *
	 * This is the guard that keeps the two from parting company: add a key to the tree
	 * without a method for it here, and this goes red rather than the key being
	 * quietly unreachable for anybody using this form.
	 *
	 * @return void
	 */
	public function testEveryKeyOfTheTreeHasAMethod(): void {
		$written = $this->everything()->toArray();

		$this->assertSame( $this->treeKeys(), array_keys( $written ) );
		$this->assertIsArray( $written['date'] );
		$this->assertSame( $this->treeKeys( 'date' ), array_keys( $written['date'] ) );
		$this->assertIsArray( $written['number_format'] );
		$this->assertSame( $this->treeKeys( 'number_format' ), array_keys( $written['number_format'] ) );
	}

	/**
	 * And every one of them survives the tree, so none is written under a name the tree
	 * would refuse.
	 *
	 * @return void
	 */
	public function testEverythingItWritesIsAcceptedByTheTree(): void {
		$processed = $this->process( $this->everything()->toArray() );

		$this->assertSame( 'name', $processed['autoescape'] );
		$this->assertSame( array( '/abs/emails' => 'emails' ), $processed['paths'] );
		$this->assertSame( 0, $processed['optimizations'] );

		$this->assertIsArray( $processed['date'] );
		$this->assertSame( 'Europe/Paris', $processed['date']['timezone'] );

		$this->assertIsArray( $processed['number_format'] );
		$this->assertSame( ' ', $processed['number_format']['thousands_separator'] );
	}

	/**
	 * The two forms are one configuration. If this ever failed, the fluent form would
	 * be a second semantics rather than a second spelling of the first.
	 *
	 * @return void
	 */
	public function testTheFluentFormAndTheArrayFormAgree(): void {
		$written = array(
			'paths'            => array( '/abs/emails' => 'emails' ),
			'strict_variables' => true,
			'autoescape'       => 'name',
			'globals'          => array(
				'menu'   => '@app.menu',
				'handle' => '@@offsetwp',
			),
			'extensions'       => array( DebugExtension::class ),
			'date'             => array( 'format' => 'd/m/Y' ),
		);

		$built = TwigConfig::create()
			->path( '/abs/emails', 'emails' )
			->strictVariables( true )
			->autoescape( Escaping::ByTemplateName )
			->globalService( 'menu', 'app.menu' )
			->globalLiteral( 'handle', '@offsetwp' )
			->extension( DebugExtension::class )
			->dateFormat( 'd/m/Y' )
			->toArray();

		$this->assertSame( $this->process( $written ), $this->process( $built ) );
	}

	/**
	 * A directory, a global and an extension each accumulate: they are lists of things
	 * a project adds to, not settings it replaces.
	 *
	 * @return void
	 */
	public function testDirectoriesGlobalsAndExtensionsAccumulate(): void {
		$built = TwigConfig::create()
			->path( '/abs/a', 'a' )
			->path( '/abs/b' )
			->global( 'one', 1 )
			->global( 'two', 2 )
			->extension( DebugExtension::class )
			->extensions( Escaping::class, TwigConfig::class )
			->toArray();

		$this->assertSame(
			array(
				'/abs/a' => 'a',
				'/abs/b' => null,
			),
			$built['paths']
		);
		$this->assertSame(
			array(
				'one' => 1,
				'two' => 2,
			),
			$built['globals']
		);
		$this->assertSame(
			array( DebugExtension::class, Escaping::class, TwigConfig::class ),
			$built['extensions']
		);
	}

	/**
	 * The two conventions a reader could only learn from the documentation — "@" names
	 * a service, "@@" escapes a literal one — become two methods that say which they
	 * mean.
	 *
	 * @return void
	 */
	public function testTheServiceAndLiteralConventionsBecomeMethods(): void {
		$built = TwigConfig::create()
			->globalService( 'menu', 'app.menu' )
			->globalLiteral( 'handle', '@offsetwp' )
			->globalLiteral( 'plain', 'offsetwp' )
			->toArray();

		$this->assertSame(
			array(
				'menu'   => '@app.menu',
				'handle' => '@@offsetwp',
				'plain'  => 'offsetwp',
			),
			$built['globals']
		);
	}

	/**
	 * The three shapes of "autoescape" and the three of "cache", each its own method
	 * rather than one method taking a union nobody can guess.
	 *
	 * @return void
	 */
	public function testEachShapeOfAutoescapeAndCacheHasItsOwnMethod(): void {
		$this->assertSame( 'js', TwigConfig::create()->autoescape( Escaping::Js )->toArray()['autoescape'] );
		$this->assertSame( 'custom', TwigConfig::create()->autoescape( 'custom' )->toArray()['autoescape'] );
		$this->assertFalse( TwigConfig::create()->noAutoescape()->toArray()['autoescape'] );
		$this->assertSame(
			array( Escaping::class, 'guess' ),
			TwigConfig::create()->autoescapeWith( Escaping::class, 'guess' )->toArray()['autoescape']
		);

		$this->assertSame( '/abs/cache', TwigConfig::create()->cacheDirectory( '/abs/cache' )->toArray()['cache'] );
		$this->assertSame( '@app.cache', TwigConfig::create()->cacheService( 'app.cache' )->toArray()['cache'] );
		$this->assertFalse( TwigConfig::create()->noCache()->toArray()['cache'] );
	}

	/**
	 * The alias this object writes under is the one the framework derives from the
	 * bundle's own name. A configuration handed to any other key is read by nobody and
	 * says nothing about it.
	 *
	 * @return void
	 */
	public function testTheAliasItWritesUnderIsTheOneTheFrameworkDerives(): void {
		$extension = ( new TwigBundle() )->getContainerExtension();

		$this->assertInstanceOf( BundleExtension::class, $extension );
		$this->assertSame( $extension->getAlias(), TwigBundle::ALIAS );
	}
}
