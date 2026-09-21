<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Integration
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Integration;

use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\ExtensionPass;
use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\LoaderPass;
use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\RuntimePass;
use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\TaggedServicesTrait;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\AttributedVatExtension;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\CurrencyRates;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\DiscountedVatExtension;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\MemoryLoader;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\PlainRuntime;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\PriceExtension;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\RatesRuntime;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\RecordingNodeVisitor;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\ShopExtension;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\TemplateInfoExtension;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\TokenParser\GreetTokenParser;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\KernelTestCase;
use OffsetWP\Bundle\TwigBundle\TwigBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Environment;
use Twig\Source;

/**
 * Every way into Twig, end to end, through a real kernel.
 */
#[CoversClass( TwigBundle::class )]
#[CoversClass( ExtensionPass::class )]
#[CoversClass( LoaderPass::class )]
#[CoversClass( RuntimePass::class )]
#[CoversTrait( TaggedServicesTrait::class )]
final class ExtensionPointsTest extends KernelTestCase {
	/**
	 * The arrangement the README documents, through a real kernel: autoconfiguration
	 * on, and the tag written by hand to set a priority.
	 *
	 * The container keeps both occurrences — it skips an autoconfigured tag only when
	 * its attributes are identical to one already there, and an attribute-less tag is
	 * not identical to a priority — so this is not a contrived shape. Before the tag
	 * was counted once per service, booting this kernel raised "is registered twice:
	 * by the service app.shop and by the service app.shop".
	 *
	 * @return void
	 */
	public function testAutoconfigurationAndAHandWrittenPriorityRegisterOneExtension(): void {
		$kernel = $this->boot(
			array(),
			static function ( ContainerBuilder $container ): void {
				$container->register( 'app.shop', ShopExtension::class )
					->setAutoconfigured( true )
					->addTag( ExtensionPass::EXTENSION_TAG, array( 'priority' => 10 ) );
			}
		);

		$this->assertSame( "120\n", $this->twig( $kernel )->render( 'extension/vat.twig', array( 'amount' => 100 ) ) );
	}

	/**
	 * Render a template whose filter two extensions both declare, each at the priority
	 * given.
	 *
	 * @param int $shop     The priority of the extension adding VAT.
	 * @param int $discount The priority of the extension adding none.
	 * @return string
	 */
	private function renderVatDuel( int $shop, int $discount ): string {
		$kernel = $this->boot(
			array(),
			static function ( ContainerBuilder $container ) use ( $shop, $discount ): void {
				$container->register( 'app.shop', ShopExtension::class )
					->addTag( ExtensionPass::EXTENSION_TAG, array( 'priority' => $shop ) );
				$container->register( 'app.discount', DiscountedVatExtension::class )
					->addTag( ExtensionPass::EXTENSION_TAG, array( 'priority' => $discount ) );
			}
		);

		return $this->twig( $kernel )->render( 'extension/vat.twig', array( 'amount' => 100 ) );
	}

	/**
	 * A tagged extension and an attributed class declaring one filter name, ordered by
	 * priority across the two.
	 *
	 * They used to be appended one register after the other, every tagged extension
	 * and then every attributed class, so an attributed method always reached Twig
	 * last and therefore always won — at any priority, including one written expressly
	 * to lose. The README says priority decides which declaration survives, and that
	 * is only true if the two can be compared.
	 *
	 * Both orderings share one fixture: a compiled template is named after a hash that
	 * includes the extensions in the order they were added, so two orderings are two
	 * compiled classes.
	 *
	 * @return void
	 */
	public function testPriorityOrdersAnAttributedClassAgainstATaggedExtension(): void {
		$this->assertSame( "100\n", $this->renderAttributedVatDuel( 10, 0 ) );
		$this->assertSame( "120\n", $this->renderAttributedVatDuel( 0, 10 ) );
	}

	/**
	 * Render the vat fixture through a kernel where the filter is declared twice: by a
	 * tagged extension that adds the tax, and by an attributed class that does not.
	 *
	 * @param int $shop       The priority of the tagged extension.
	 * @param int $attributed The priority of the attributed class.
	 * @return string
	 */
	private function renderAttributedVatDuel( int $shop, int $attributed ): string {
		$kernel = $this->boot(
			array(),
			static function ( ContainerBuilder $container ) use ( $shop, $attributed ): void {
				$container->register( 'app.shop', ShopExtension::class )
					->addTag( ExtensionPass::EXTENSION_TAG, array( 'priority' => $shop ) );
				$container->register( 'app.attributed_vat', AttributedVatExtension::class )
					->addTag( ExtensionPass::ATTRIBUTE_EXTENSION_TAG, array( 'priority' => $attributed ) );
			}
		);

		return $this->twig( $kernel )->render( 'extension/vat.twig', array( 'amount' => 100 ) );
	}

	/**
	 * Render a global fixture through a kernel configured with the given globals.
	 *
	 * @param array<string, mixed> $globals  The globals a host would write.
	 * @param string               $template The fixture of this case.
	 * @return string
	 */
	private function renderGlobal( array $globals, string $template ): string {
		$kernel = $this->boot(
			array( 'globals' => $globals ),
			static function ( ContainerBuilder $container ): void {
				$container->register( 'app.rates', CurrencyRates::class );
			}
		);

		return $this->twig( $kernel )->render( $template );
	}

	/**
	 * Render the greet tag through a kernel that knows about its parser and nothing
	 * else, with the yield option set either way.
	 *
	 * @param bool $use_yield Whether compiled templates yield their output.
	 * @return string
	 */
	private function renderGreetTag( bool $use_yield ): string {
		$kernel = $this->boot(
			array( 'use_yield' => $use_yield ),
			static function ( ContainerBuilder $container ): void {
				$container->register( GreetTokenParser::class, GreetTokenParser::class )
					->setAutowired( true )
					->setAutoconfigured( true );
			}
		);

		return $this->twig( $kernel )->render( 'tag/greet.twig', array( 'name' => 'Jérôme' ) );
	}

	/**
	 * Boot with the attributed fixtures registered the way a host's own
	 * config/services.php registers its App\ namespace: autowired, autoconfigured, and
	 * with no Twig tag written anywhere.
	 *
	 * @param string ...$classes The attributed classes this case needs.
	 * @return Environment
	 */
	private function withAttributed( string ...$classes ): Environment {
		return $this->twig(
			$this->boot(
				array(),
				static function ( ContainerBuilder $container ) use ( $classes ): void {
					$container->register( CurrencyRates::class, CurrencyRates::class );

					foreach ( $classes as $attributed ) {
						$container->register( $attributed, $attributed )
							->setAutowired( true )
							->setAutoconfigured( true );
					}
				}
			)
		);
	}

	/**
	 * Autoconfiguration is what turns an interface into a tag, and a host enables it
	 * in its own _defaults. A service registered by a test says so for itself.
	 *
	 * @return void
	 */
	public function testAnExtensionIsRegisteredByItsInterfaceAlone(): void {
		$kernel = $this->boot(
			array(),
			static function ( ContainerBuilder $container ): void {
				$container->register( 'app.shop', ShopExtension::class )->setAutoconfigured( true );
			}
		);

		$this->assertSame( "120\n", $this->twig( $kernel )->render( 'extension/vat.twig', array( 'amount' => 100 ) ) );
	}

	/**
	 * The same extension without autoconfiguration, tagged by hand. This is the form
	 * a project uses when autoconfiguration is off, and the only form that can carry
	 * a priority.
	 *
	 * @return void
	 */
	public function testAnExtensionIsRegisteredByAnExplicitTag(): void {
		$kernel = $this->boot(
			array(),
			static function ( ContainerBuilder $container ): void {
				$container->register( 'app.shop', ShopExtension::class )
					->addTag( ExtensionPass::EXTENSION_TAG );
			}
		);

		$this->assertSame( "120\n", $this->twig( $kernel )->render( 'extension/vat.twig', array( 'amount' => 100 ) ) );
	}

	/**
	 * One line of configuration, no service declaration at all. This is the install
	 * path for every extension class that takes no constructor argument, which is
	 * five of the seven twig/*-extra packages.
	 *
	 * @return void
	 */
	public function testAnExtensionIsRegisteredByTheConfigurationKey(): void {
		$kernel = $this->boot( array( 'extensions' => array( ShopExtension::class ) ) );

		$this->assertSame( "120\n", $this->twig( $kernel )->render( 'extension/vat.twig', array( 'amount' => 100 ) ) );
	}

	/**
	 * A runtime that says what it is reaches the locator with no tag written
	 * anywhere, and Twig can then load it by class name.
	 *
	 * @return void
	 */
	public function testARuntimeIsRegisteredByItsInterface(): void {
		$kernel = $this->boot(
			array(),
			static function ( ContainerBuilder $container ): void {
				$container->register( 'app.rates', RatesRuntime::class )->setAutoconfigured( true );
			}
		);

		$this->assertInstanceOf( RatesRuntime::class, $this->twig( $kernel )->getRuntime( RatesRuntime::class ) );
	}

	/**
	 * And a runtime that implements nothing reaches it by its tag. This is not a
	 * fallback: two of the twig/*-extra packages ship exactly this shape, so the tag
	 * is the primary path for them and autoconfiguration alone would miss both.
	 *
	 * @return void
	 */
	public function testARuntimeWithoutTheInterfaceIsRegisteredByItsTag(): void {
		$kernel = $this->boot(
			array(),
			static function ( ContainerBuilder $container ): void {
				$container->register( 'app.plain', PlainRuntime::class )->addTag( RuntimePass::RUNTIME_TAG );
			}
		);

		$this->assertInstanceOf( PlainRuntime::class, $this->twig( $kernel )->getRuntime( PlainRuntime::class ) );
	}

	/**
	 * The headline of the package: one method, one attribute, nothing else. The class
	 * has a constructor dependency and neither it nor the dependency is named anywhere
	 * near Twig.
	 *
	 * @return void
	 */
	public function testAFilterIsRegisteredFromAnAttribute(): void {
		$this->assertSame( "12,50 EUR\n", $this->withAttributed( PriceExtension::class )->render( 'attribute/price.twig' ) );
	}

	/**
	 * The same for a function.
	 *
	 * @return void
	 */
	public function testAFunctionIsRegisteredFromAnAttribute(): void {
		$this->assertSame( "1.1\n", $this->withAttributed( PriceExtension::class )->render( 'attribute/rate.twig' ) );
	}

	/**
	 * And for a test, which reads as "is free" in a template.
	 *
	 * @return void
	 */
	public function testATestIsRegisteredFromAnAttribute(): void {
		$twig = $this->withAttributed( PriceExtension::class );

		$this->assertSame( 'free', $twig->render( 'attribute/free.twig', array( 'amount' => 0 ) ) );
		$this->assertSame( 'paid', $twig->render( 'attribute/free.twig', array( 'amount' => 500 ) ) );
	}

	/**
	 * A static attributed method costs less than a non-static one, and the difference
	 * is visible in the compiled template: a direct static call, with no trip through
	 * the runtime loader and therefore no object to construct.
	 *
	 * @return void
	 */
	public function testAStaticAttributedMethodWorks(): void {
		$compiled = $this->withAttributed( PriceExtension::class )->compileSource(
			new Source( '{% if amount is free %}free{% else %}paid{% endif %}', 'attribute/free.twig' )
		);

		$this->assertStringContainsString( PriceExtension::class . '::isFree', $compiled );
		$this->assertStringNotContainsString( 'getRuntime', $compiled );
	}

	/**
	 * A first parameter type-hinted as the environment is passed by Twig rather than
	 * expected from the template. The kernel is given an unusual charset so that the
	 * assertion cannot pass on a constant.
	 *
	 * @return void
	 */
	public function testAnAttributedMethodCanReceiveTheEnvironment(): void {
		$kernel = $this->kernel(
			array(),
			static function ( ContainerBuilder $container ): void {
				$container->register( TemplateInfoExtension::class, TemplateInfoExtension::class )
					->setAutowired( true )
					->setAutoconfigured( true );
			}
		);

		$kernel->setCharset( 'ISO-8859-15' );
		$kernel->boot();

		$this->assertSame( "ISO-8859-15\n", $this->twig( $kernel )->render( 'attribute/charset.twig' ) );
	}

	/**
	 * Twig's attributes are repeatable, so one implementation can answer to two names.
	 *
	 * @return void
	 */
	public function testARepeatedAttributeRegistersBothNames(): void {
		$this->assertSame(
			"A!B!\n",
			$this->withAttributed( TemplateInfoExtension::class )->render( 'attribute/repeated.twig' )
		);
	}

	/**
	 * A tag is one class for the parser and one for the node, and no registration.
	 * The parser extends Twig's abstract token parser, which implements the interface
	 * autoconfiguration looks for.
	 *
	 * @return void
	 */
	public function testACustomTagIsRegisteredByItsInterface(): void {
		$this->assertSame( 'Hello, Jérôme', $this->renderGreetTag( false ) );
	}

	/**
	 * The node carries Twig's yield-ready attribute, so the same tag renders
	 * identically with the yield option on. A node written today should carry it.
	 *
	 * @return void
	 */
	public function testACustomTagRendersWithUseYieldEnabled(): void {
		$this->assertSame( 'Hello, Jérôme', $this->renderGreetTag( true ) );
	}

	/**
	 * A node visitor reaches compilation with no tag written anywhere, and the proof
	 * is that it ran: it records itself on the root node of every template it sees.
	 *
	 * @return void
	 */
	public function testANodeVisitorIsRegisteredByItsInterface(): void {
		RecordingNodeVisitor::$visited = array();

		$kernel = $this->boot(
			array(),
			static function ( ContainerBuilder $container ): void {
				$container->register( RecordingNodeVisitor::class, RecordingNodeVisitor::class )
					->setArguments( array( 'only' ) )
					->setAutoconfigured( true );
			}
		);

		$this->assertSame( "visited\n", $this->twig( $kernel )->render( 'visitor/target.twig' ) );
		$this->assertSame( array( 'only' ), RecordingNodeVisitor::$visited );
	}

	/**
	 * Twig sorts visitors by the priority they report themselves, and both of these
	 * report the same one — so what decides the order between them is the order they
	 * were added in, which is the tag priority this bundle sorts on.
	 *
	 * @return void
	 */
	public function testNodeVisitorsRunInPriorityOrder(): void {
		RecordingNodeVisitor::$visited = array();

		$kernel = $this->boot(
			array(),
			static function ( ContainerBuilder $container ): void {
				foreach ( array(
					'last'  => -10,
					'first' => 10,
				) as $label => $priority ) {
					$container->register( 'app.visitor.' . $label, RecordingNodeVisitor::class )
						->setArguments( array( $label ) )
						->addTag( ExtensionPass::NODE_VISITOR_TAG, array( 'priority' => $priority ) );
				}
			}
		);

		$this->twig( $kernel )->render( 'visitor/ordered.twig' );

		$this->assertSame( array( 'first', 'last' ), RecordingNodeVisitor::$visited );
	}

	/**
	 * A host loader at a higher priority is consulted first, and the filesystem is
	 * the fallback. A template only the second loader has still resolves, which is
	 * the whole point of a chain.
	 *
	 * @return void
	 */
	public function testATemplateFromTheSecondLoaderInTheChainResolves(): void {
		$kernel = $this->boot(
			array(),
			static function ( ContainerBuilder $container ): void {
				$container->register( 'app.memory', MemoryLoader::class )
					->setArguments( array( array( 'memory/only.twig' => 'Kept in memory.' ) ) )
					->addTag( LoaderPass::LOADER_TAG, array( 'priority' => 10 ) );
			}
		);

		$twig = $this->twig( $kernel );

		$this->assertSame( 'Kept in memory.', $twig->render( 'memory/only.twig' ) );
		$this->assertSame( "Hello, Jérôme!\n", $twig->render( 'hello.twig', array( 'name' => 'Jérôme' ) ) );
	}

	/**
	 * The plain case: a value every template can read without being handed it.
	 *
	 * @return void
	 */
	public function testAScalarGlobalIsAvailableInTemplates(): void {
		$this->assertSame(
			"Étoile Malraux\n",
			$this->renderGlobal( array( 'site_name' => 'Étoile Malraux' ), 'global/scalar.twig' )
		);
	}

	/**
	 * A container parameter resolves before this bundle ever sees the value, which is
	 * why nothing here has to know about parameters at all.
	 *
	 * @return void
	 */
	public function testAParameterGlobalIsResolved(): void {
		$this->assertSame(
			"Étoile Malraux\n",
			$this->renderGlobal( array( 'site' => '%app.name%' ), 'global/parameter.twig' )
		);
	}

	/**
	 * A global is whatever the host wrote. Only a string is ever read as anything
	 * other than itself — the "@" prefix — so a number, a boolean and an array reach
	 * the template untouched, and a name with a dash in it stays spelled that way,
	 * which is what key normalisation being off is for.
	 *
	 * @return void
	 */
	public function testAGlobalIsHandedOverAsItWasWritten(): void {
		$globals = $this->twig(
			$this->boot(
				array(
					'globals' => array(
						'count'     => 3,
						'enabled'   => true,
						'menu'      => array( 'home', 'contact' ),
						'site-name' => 'Étoile Malraux',
					),
				)
			)
		)->getGlobals();

		$this->assertSame( 3, $globals['count'] );
		$this->assertTrue( $globals['enabled'] );
		$this->assertSame( array( 'home', 'contact' ), $globals['menu'] );
		$this->assertSame( 'Étoile Malraux', $globals['site-name'] );
	}

	/**
	 * A value starting with a single "@" names a host service, and the template gets
	 * the object itself.
	 *
	 * @return void
	 */
	public function testAServiceGlobalIsInjected(): void {
		$this->assertSame( "1.1\n", $this->renderGlobal( array( 'rates' => '@app.rates' ), 'global/service.twig' ) );
	}

	/**
	 * And a doubled one escapes, so a global can hold a literal handle.
	 *
	 * @return void
	 */
	public function testADoubleAtSignEscapesToALiteralAtSign(): void {
		$this->assertSame( "@twig\n", $this->renderGlobal( array( 'handle' => '@@twig' ), 'global/literal.twig' ) );
	}

	/**
	 * Two extensions declaring one filter name, and the priority decides. The result
	 * reads backwards until you see why: higher priority reaches Twig first, Twig lets
	 * the last registration of a name win, so the lower priority is the one whose
	 * implementation survives. Overriding a filter is a legitimate thing to want, and
	 * this is the knob for it.
	 *
	 * Both orderings render one fixture. A compiled template is named after a hash of
	 * the environment's options and its extension signature, and that signature is the
	 * extension class names in the order they were added — so two orderings are
	 * already two compiled classes, and the two byte-identical copies this used to
	 * need were needed for nothing.
	 *
	 * @return void
	 */
	public function testExtensionPriorityDecidesWhichFilterWins(): void {
		$this->assertSame( "100\n", $this->renderVatDuel( 10, 0 ) );
		$this->assertSame( "120\n", $this->renderVatDuel( 0, 10 ) );
	}

	/**
	 * A project with no templates directory and no configured path gets no filesystem
	 * loader at all, so a host loader is not merely first in a chain — it is the only
	 * one there is.
	 *
	 * @return void
	 */
	public function testACustomLoaderReplacesTheFilesystemLoader(): void {
		$kernel = $this->boot(
			array(),
			static function ( ContainerBuilder $container ): void {
				$container->register( 'app.memory', MemoryLoader::class )
					->setArguments( array( array( 'only.twig' => 'from the host loader' ) ) )
					->addTag( LoaderPass::LOADER_TAG );
			},
			'ProjectWithoutTemplates'
		);

		$twig = $this->twig( $kernel );

		$this->assertInstanceOf( MemoryLoader::class, $twig->getLoader() );
		$this->assertSame( 'from the host loader', $twig->render( 'only.twig' ) );
	}

	/**
	 * A loader is one class, like every other way into Twig: implementing the
	 * interface is enough, and the tag is only needed to give it a priority.
	 *
	 * @return void
	 */
	public function testACustomLoaderIsRegisteredByItsInterface(): void {
		$kernel = $this->boot(
			array(),
			static function ( ContainerBuilder $container ): void {
				$container->register( 'app.memory', MemoryLoader::class )
					->setArguments( array( array( 'by-interface.twig' => 'from the host loader' ) ) )
					->setAutoconfigured( true );
			},
			'ProjectWithoutTemplates'
		);

		$twig = $this->twig( $kernel );

		$this->assertInstanceOf( MemoryLoader::class, $twig->getLoader() );
		$this->assertSame( 'from the host loader', $twig->render( 'by-interface.twig' ) );
	}

	/**
	 * The configuration key has to work even when the host also declares the class as
	 * a service under its own name — the form this package's own recipes teach.
	 *
	 * The library captures the host's definitions before it merges an extension's own
	 * and restores them afterwards, so anything this bundle registers under an id the
	 * host already uses is discarded, tag included. The definitions are prefixed for
	 * that reason, and this asserts the outcome rather than the reason: the host's
	 * service is not autoconfigured and carries no tag, so only the configuration key
	 * can make the filter exist.
	 *
	 * @return void
	 */
	public function testTheConfigurationKeyWorksBesideAHostServiceOfTheSameClass(): void {
		$kernel = $this->boot(
			array( 'extensions' => array( ShopExtension::class ) ),
			static function ( ContainerBuilder $container ): void {
				$container->register( ShopExtension::class, ShopExtension::class );
			}
		);

		$this->assertSame( "120\n", $this->twig( $kernel )->render( 'extension/vat.twig', array( 'amount' => 100 ) ) );
	}
}
