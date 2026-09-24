<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Integration
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Integration;

use OffsetWP\Bundle\TwigBundle\Loader\NoTemplateSourceLoader;
use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\ExtensionPass;
use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\LoaderPass;
use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\OwnershipPass;
use OffsetWP\Bundle\TwigBundle\Environment\CoreSettings;
use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\RuntimePass;
use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\TaggedServicesTrait;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\HiddenFilterExtension;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\CurrencyRates;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\NotAnExtension;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\PriceExtension;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\ShopExtension;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\KernelTestCase;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\TestKernel;
use OffsetWP\Bundle\TwigBundle\Twig;
use OffsetWP\Bundle\TwigBundle\TwigBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Twig\Error\LoaderError;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Every failure of this bundle names the exact path, service or key at fault.
 */
#[CoversClass( TwigBundle::class )]
#[CoversClass( NoTemplateSourceLoader::class )]
#[CoversClass( OwnershipPass::class )]
#[CoversClass( ExtensionPass::class )]
#[CoversClass( LoaderPass::class )]
#[CoversClass( RuntimePass::class )]
#[CoversClass( Twig::class )]
#[CoversTrait( TaggedServicesTrait::class )]
final class ErrorMessagesTest extends KernelTestCase {
	/**
	 * The three services a project can plausibly write in its own config/services.php
	 * under the same id this bundle uses.
	 *
	 * @return array<string, array{string}>
	 */
	public static function servicesTheBundleOwns(): array {
		return array(
			'the environment'       => array( Environment::class ),
			'the filesystem loader' => array( FilesystemLoader::class ),
			'the core settings'     => array( CoreSettings::class ),
		);
	}

	/**
	 * A project that writes one of these ids in its own config/services.php wins it.
	 * The library captures a host's definitions before it merges a bundle's and
	 * restores them afterwards, so the project's definition replaces this bundle's —
	 * and everything the configuration had put on ours goes with it: the options, the
	 * paths, the globals, the extensions.
	 *
	 * The result built and answered to the right id, so nothing failed. The twig.*
	 * configuration was read, validated and thrown away, and a project in that state
	 * reports no template directory while the directory sits in its configuration
	 * file.
	 *
	 * @param string $id The service id the project redefined.
	 * @return void
	 */
	#[DataProvider( 'servicesTheBundleOwns' )]
	public function testRedefiningAServiceThisBundleBuildsIsRefused( string $id ): void {
		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage(
			sprintf( 'The service "%s" is defined by this project as well as by the Twig bundle', $id )
		);

		$this->boot(
			array(),
			static function ( ContainerBuilder $container ) use ( $id ): void {
				$container->register( $id, \stdClass::class );
			}
		);
	}

	/**
	 * A cache written as "@service" and pointing at nothing. Left alone the container
	 * refuses to build on "the service Twig\\Environment has a dependency on a
	 * non-existent service", which names what was asked for and nothing about which
	 * key asked — the same reason a global pointing at nothing is checked here too.
	 *
	 * @return void
	 */
	public function testACacheReferencingAnUnknownServiceNamesTheKey(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage(
			'The "twig.cache" key references the service "app.nowhere", which is not defined.'
		);

		$this->boot( array( 'cache' => '@app.nowhere' ) );
	}

	/**
	 * A relative path resolves against the current working directory, which under a
	 * web server is whatever the process happened to start in. Refusing it at build
	 * time is cheaper than debugging it, and the message has to name the key so that
	 * the reader knows which of their entries is the problem.
	 *
	 * @return void
	 */
	public function testARelativeTemplatePathIsRefusedAndNamesTheKey(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage(
			'The "twig.paths" key "templates" is not an absolute path. Twig resolves relative paths against the current working directory, which is unpredictable under a web server. Use an absolute path, for example "%kernel.root_path%/templates".'
		);

		( new TestKernel( $this->project(), array( 'paths' => array( 'templates' => null ) ) ) )->boot();
	}

	/**
	 * A directory that is not there is a typo, and a container that refuses to build
	 * is cheaper to debug than a page that renders the wrong thing.
	 *
	 * @return void
	 */
	public function testAMissingTemplateDirectoryIsRefusedAndNamesTheAbsolutePath(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage(
			'The "twig.paths" directory "/nowhere/at/all" does not exist. Create it, or correct the entry in "twig.paths".'
		);

		( new TestKernel( $this->project(), array( 'paths' => array( '/nowhere/at/all' => null ) ) ) )->boot();
	}

	/**
	 * The default directory may be missing; one the host named may not.
	 *
	 * @return void
	 */
	public function testAnExplicitDefaultPathThatIsMissingIsRefused(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage(
			'The "twig.default_path" directory "/nowhere/at/all" does not exist. Create it, point "twig.default_path" elsewhere, or list your template directories under "twig.paths".'
		);

		( new TestKernel( $this->project(), array( 'default_path' => '/nowhere/at/all' ) ) )->boot();
	}

	/**
	 * A project that has not created its templates directory yet is unfinished, not
	 * broken: it boots. The failure arrives when a template is actually asked for,
	 * and it names the directory that was looked for and the two ways out.
	 *
	 * @return void
	 */
	public function testRenderingWithNoTemplateSourceNamesTheDefaultDirectory(): void {
		$root = $this->rootPath( 'ProjectWithoutTemplates' );

		$this->assertDirectoryDoesNotExist( $root . '/templates' );

		$twig = $this->twig( $this->boot( array(), null, 'ProjectWithoutTemplates' ) );

		$this->expectException( LoaderError::class );
		$this->expectExceptionMessage(
			sprintf(
				'Twig has no template source. The default directory "%s" does not exist, and there is no "twig.paths" entry and no service tagged "twig.loader". Create that directory, or configure "twig.paths".',
				$root . '/templates'
			)
		);

		$twig->render( 'hello.twig' );
	}

	/**
	 * A typo in a class name would otherwise surface as "Unknown filter" on whichever
	 * page used the filter first, which says nothing about where to look.
	 *
	 * @return void
	 */
	public function testAnUnknownExtensionClassIsRefusedAndNamesTheKey(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage(
			'The class "Acme\\Nope" listed under "twig.extensions" does not exist. Check the spelling, or install the package that ships it.'
		);

		$this->boot( array( 'extensions' => array( 'Acme\\Nope' ) ) );
	}

	/**
	 * A class that exists but is not an extension: the message names the interface it
	 * has to implement, so the reader knows what to change rather than only that
	 * something is wrong.
	 *
	 * @return void
	 */
	public function testANonExtensionClassIsRefusedAndNamesTheInterface(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage(
			sprintf(
				'The class "%s" listed under "twig.extensions" does not implement "Twig\\Extension\\ExtensionInterface".',
				NotAnExtension::class
			)
		);

		$this->boot( array( 'extensions' => array( NotAnExtension::class ) ) );
	}

	/**
	 * The realistic shape of the mistake: an extension declared as a service for its
	 * constructor arguments, and later added to the configuration key as well.
	 *
	 * @return void
	 */
	public function testADuplicateExtensionNamesBothSources(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessageMatches(
			sprintf(
				'/^The Twig extension "%s" is registered twice: .*the "twig\.extensions" config key\./s',
				preg_quote( ShopExtension::class, '/' )
			)
		);

		$this->boot(
			array( 'extensions' => array( ShopExtension::class ) ),
			static function ( ContainerBuilder $container ): void {
				$container->register( 'app.shop', ShopExtension::class )
					->addTag( ExtensionPass::EXTENSION_TAG );
			}
		);
	}

	/**
	 * Boot with one wrongly tagged service, expecting the refusal that names it.
	 *
	 * @param string $tag      The tag to put on a class that cannot honour it.
	 * @param string $expected The interface the message must name.
	 * @return void
	 */
	private function expectMistagRefusal( string $tag, string $expected ): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage(
			sprintf(
				'The service "app.thing" is tagged "%s" but its class "%s" does not implement "%s".',
				$tag,
				NotAnExtension::class,
				$expected
			)
		);

		$this->boot(
			array(),
			static function ( ContainerBuilder $container ) use ( $tag ): void {
				$container->register( 'app.thing', NotAnExtension::class )->addTag( $tag );
			}
		);
	}

	/**
	 * A tag on the wrong class is a silent no-op at best and a type error from inside
	 * Twig at worst. The message names the service, its class and the interface.
	 *
	 * @return void
	 */
	public function testAMistaggedExtensionServiceIsRefused(): void {
		$this->expectMistagRefusal( ExtensionPass::EXTENSION_TAG, 'Twig\\Extension\\ExtensionInterface' );
	}

	/**
	 * The same for a loader, where the failure would otherwise be a type error inside
	 * the environment's constructor.
	 *
	 * @return void
	 */
	public function testAMistaggedLoaderServiceIsRefused(): void {
		$this->expectMistagRefusal( LoaderPass::LOADER_TAG, 'Twig\\Loader\\LoaderInterface' );
	}

	/**
	 * The same for a token parser.
	 *
	 * @return void
	 */
	public function testAMistaggedTokenParserServiceIsRefused(): void {
		$this->expectMistagRefusal( ExtensionPass::TOKEN_PARSER_TAG, 'Twig\\TokenParser\\TokenParserInterface' );
	}

	/**
	 * The same for a node visitor.
	 *
	 * @return void
	 */
	public function testAMistaggedNodeVisitorServiceIsRefused(): void {
		$this->expectMistagRefusal( ExtensionPass::NODE_VISITOR_TAG, 'Twig\\NodeVisitor\\NodeVisitorInterface' );
	}

	/**
	 * The container scans public methods only, while Twig's attribute reader scans
	 * every one of them. A non-public attributed method therefore produces a filter
	 * that exists and fails at the first call, with a message about visibility and
	 * nothing about Twig. This refuses it while the container builds instead, naming
	 * the class, the method and the attribute.
	 *
	 * @return void
	 */
	public function testANonPublicAttributedMethodIsRefusedAndNamesTheMethod(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage(
			sprintf(
				'"%s::hidden()" carries #[AsTwigFilter] but is not public. Twig cannot call it. Make the method public.',
				HiddenFilterExtension::class
			)
		);

		$this->boot(
			array(),
			static function ( ContainerBuilder $container ): void {
				$container->register( HiddenFilterExtension::class, HiddenFilterExtension::class )
					->setAutowired( true )
					->setAutoconfigured( true );
			}
		);
	}

	/**
	 * Left alone, the container would still refuse to build, with a message about a
	 * dependency of the environment and nothing about which global asked for it.
	 *
	 * @return void
	 */
	public function testAGlobalPointingAtAnUnknownServiceIsRefused(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage(
			'The Twig global "menu" references the service "app.menu_builder", which is not defined.'
		);

		$this->boot( array( 'globals' => array( 'menu' => '@app.menu_builder' ) ) );
	}

	/**
	 * A kernel that never registered this bundle has no Twig to give. The message
	 * names that kernel's own root path, because in a request with several kernels
	 * knowing which one is the whole question.
	 *
	 * @return void
	 */
	public function testOfOnAKernelWithoutTheBundleNamesTheRootPath(): void {
		$kernel = $this->kernel( array(), null, 'ProjectWithoutBundle' );
		$kernel->boot();

		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage(
			sprintf(
				'The kernel rooted at "%s" has no "twig" service. Add %s to its config/bundles.php.',
				$this->rootPath( 'ProjectWithoutBundle' ),
				TwigBundle::class
			)
		);

		Twig::of( $kernel );
	}

	/**
	 * One kernel, one spelling of its root. A kernel keeps the string its constructor
	 * was given, while the parameter it publishes is that string resolved, so a root
	 * written with a trailing slash — or reached through a symlink — used to be named
	 * one way by the booted failure and another by the non-booted one. In a request
	 * with several kernels, two spellings of one root is the reader's whole problem.
	 *
	 * @return void
	 */
	public function testAKernelIsNamedTheSameWayBootedAndNot(): void {
		$root = $this->project( 'ProjectWithoutBundle' ) . DIRECTORY_SEPARATOR;

		try {
			Twig::of( new TestKernel( $root ) );
			$this->fail( 'A non-booted kernel was expected to be refused.' );
		} catch ( \LogicException $error ) {
			$this->assertStringContainsString( sprintf( 'rooted at "%s"', $this->rootPath( 'ProjectWithoutBundle' ) ), $error->getMessage() );
		}

		$kernel = new TestKernel( $root );
		$kernel->boot();

		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( sprintf( 'The kernel rooted at "%s" has no "twig" service.', $this->rootPath( 'ProjectWithoutBundle' ) ) );

		Twig::of( $kernel );
	}

	/**
	 * And a kernel that has not booted has no container at all. The framework's own
	 * message for that names no kernel, which in a request with several is the one
	 * thing the reader needs.
	 *
	 * @return void
	 */
	public function testOfOnANonBootedKernelNamesTheRootPath(): void {
		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage(
			sprintf(
				'Cannot retrieve Twig from a non-booted kernel rooted at "%s". Boot that kernel before asking it for Twig.',
				$this->rootPath()
			)
		);

		Twig::of( $this->kernel() );
	}

	/**
	 * The shortcut works because a kernel registered itself when it booted. Called
	 * before that, it says so, and names every way forward: boot the kernel, ask a
	 * specific one for its own service, or look at the two reasons a kernel that did
	 * boot might still be carrying no Twig bundle.
	 *
	 * @return void
	 */
	public function testCallingTwigBeforeAnyKernelBootedExplainsWhatToDo(): void {
		$this->assertFalse( Twig::booted() );

		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage(
			sprintf(
				'No kernel carrying the Twig bundle has booted. If yours has, then it is not carrying it: check that config/bundles.php lists %s and enables it for this environment, and that the kernel was built in configuration mode, because one handed a services file instead of a config directory registers no bundles at all. If it has not booted yet, boot it before calling twig() or Twig::render(), or use $kernel->service( \'twig\' ) on a specific kernel.',
				TwigBundle::class
			)
		);

		twig( 'hello.twig' );
	}

	/**
	 * Twig's own refusal of a bad cache value names neither the key nor the forms that
	 * would work.
	 *
	 * @return void
	 */
	public function testAWrongCacheValueIsRefusedAndNamesTheKey(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage(
			'The "twig.cache" key expects false, an absolute directory or an "@service_id" reference, bool given.'
		);

		$this->boot( array( 'cache' => true ) );
	}

	/**
	 * A relative cache directory is refused for the same reason a relative template
	 * path is, and the message says which reason.
	 *
	 * @return void
	 */
	public function testARelativeCachePathIsRefusedAndNamesTheKey(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage(
			'The "twig.cache" path "var/cache/twig" is not an absolute path. Twig resolves relative paths against the current working directory, which is unpredictable under a web server. Use an absolute path, for example "%kernel.root_path%/var/cache/twig".'
		);

		$this->boot( array( 'cache' => 'var/cache/twig' ) );
	}

	/**
	 * Twig indexes an attribute extension on the class it wraps, so the same attributed
	 * class registered as two services is refused by Twig itself — with a message
	 * naming neither of the two registrations. The shape is ordinary enough to meet:
	 * a namespace loaded wholesale, plus one class registered again under a short id.
	 *
	 * @return void
	 */
	public function testAnAttributedClassRegisteredTwiceNamesBothServices(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage(
			sprintf(
				'The class "%s" carries Twig attributes and is registered as two services, "%s" and "app.price". Twig holds one set of attributes per class; keep one of the two.',
				PriceExtension::class,
				PriceExtension::class
			)
		);

		$this->boot(
			array(),
			static function ( ContainerBuilder $container ): void {
				$container->register( CurrencyRates::class, CurrencyRates::class );

				foreach ( array( PriceExtension::class, 'app.price' ) as $id ) {
					$container->register( $id, PriceExtension::class )
						->setAutowired( true )
						->setAutoconfigured( true );
				}
			}
		);
	}
}
