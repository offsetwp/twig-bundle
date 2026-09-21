<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Unit
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Unit;

use OffsetWP\Bundle\TwigBundle\Environment\CoreSettings;
use OffsetWP\Bundle\TwigBundle\TwigBundle;
use OffsetWP\Framework\Bundle\BundleExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * The configuration tree, processed against a bare container builder.
 */
#[CoversClass( TwigBundle::class )]
final class ConfigurationTest extends TestCase {
	/**
	 * Run the configuration tree over a list of configuration arrays, exactly the way
	 * the container does when it merges the extension configurations.
	 *
	 * @param array<int, array<string, mixed>> $configs The configuration arrays to merge.
	 * @return array<array-key, mixed>
	 */
	private function process( array $configs ): array {
		$extension = ( new TwigBundle() )->getContainerExtension();

		$this->assertInstanceOf( BundleExtension::class, $extension );

		$configuration = $extension->getConfiguration( array(), new ContainerBuilder() );

		$this->assertNotNull( $configuration );

		return ( new Processor() )->processConfiguration( $configuration, $configs );
	}

	/**
	 * Load the extension into a bare container builder and hand it back, so that a
	 * test can read the definitions the configuration produced.
	 *
	 * @param array<string, mixed> $config    The configuration a host would write.
	 * @param string               $root_path The value of the kernel root path parameter.
	 * @return ContainerBuilder
	 */
	private function load( array $config, string $root_path ): ContainerBuilder {
		$container = new ContainerBuilder();
		$container->setParameter( 'kernel.root_path', $root_path );

		$extension = ( new TwigBundle() )->getContainerExtension();

		$this->assertInstanceOf( BundleExtension::class, $extension );

		$extension->load( array( $config ), $container );

		return $container;
	}

	/**
	 * The addPath() calls the configuration produced on the filesystem loader.
	 *
	 * @param ContainerBuilder $container The loaded container.
	 * @return array<array-key, mixed>
	 */
	private function addPathCalls( ContainerBuilder $container ): array {
		return $container->getDefinition( FilesystemLoader::class )->getMethodCalls();
	}

	/**
	 * A host that writes nothing gets the documented defaults, and only those keys.
	 *
	 * @return void
	 */
	public function testAnEmptyConfigurationProducesTheDocumentedDefaults(): void {
		$this->assertSame(
			array(
				'paths'            => array(),
				'default_path'     => TwigBundle::DEFAULT_PATH,
				'debug'            => '%kernel.is_debug%',
				'charset'          => '%kernel.charset%',
				'strict_variables' => false,
				'autoescape'       => 'html',
				'cache'            => false,
				'auto_reload'      => null,
				'optimizations'    => -1,
				'use_yield'        => false,
				'globals'          => array(),
				'extensions'       => array(),
				'date'             => array(
					'format'          => CoreSettings::DEFAULT_DATE_FORMAT,
					'interval_format' => CoreSettings::DEFAULT_INTERVAL_FORMAT,
					'timezone'        => null,
				),
				'number_format'    => array(
					'decimals'            => CoreSettings::DEFAULT_DECIMALS,
					'decimal_point'       => CoreSettings::DEFAULT_DECIMAL_POINT,
					'thousands_separator' => CoreSettings::DEFAULT_THOUSANDS_SEPARATOR,
				),
			),
			$this->process( array( array() ) )
		);
	}

	/**
	 * A typo has to name the available keys, not merely refuse the configuration.
	 *
	 * @return void
	 */
	public function testAnUnknownKeyIsRejectedAndTheMessageListsTheKnownKeys(): void {
		$this->expectException( InvalidConfigurationException::class );
		$this->expectExceptionMessageMatches(
			'/^Unrecognized option "template_dir" under "twig"\. Available options are .*"default_path".*"paths"/'
		);

		$this->process( array( array( 'template_dir' => '/tmp' ) ) );
	}

	/**
	 * Path order is template precedence: the first directory holding a given file
	 * wins, so the order the host wrote has to survive to the loader untouched.
	 *
	 * @return void
	 */
	public function testPathsAreAppendedInTheDeclaredOrder(): void {
		$unit  = __DIR__;
		$tests = dirname( __DIR__ );

		$container = $this->load(
			array(
				'paths' => array(
					$unit  => null,
					$tests => 'suite',
				),
			),
			dirname( __DIR__, 2 )
		);

		$this->assertSame(
			array(
				array( 'addPath', array( $unit, FilesystemLoader::MAIN_NAMESPACE ) ),
				array( 'addPath', array( $tests, 'suite' ) ),
			),
			$this->addPathCalls( $container )
		);
	}

	/**
	 * The default directory is a fallback, so an explicitly configured path has to
	 * be searched before it.
	 *
	 * @return void
	 */
	public function testTheDefaultPathIsAppendedLast(): void {
		$container = $this->load(
			array(
				'paths'        => array( dirname( __DIR__ ) => null ),
				'default_path' => __DIR__,
			),
			dirname( __DIR__, 2 )
		);

		$this->assertSame(
			array(
				array( 'addPath', array( dirname( __DIR__ ), FilesystemLoader::MAIN_NAMESPACE ) ),
				array( 'addPath', array( __DIR__, FilesystemLoader::MAIN_NAMESPACE ) ),
			),
			$this->addPathCalls( $container )
		);
	}

	/**
	 * A project with no templates yet must still boot. The default directory is the
	 * only path this bundle invents, so it is the only one allowed to be missing, and
	 * a missing one is simply not appended.
	 *
	 * @return void
	 */
	public function testTheDefaultPathIsSkippedWhenTheDirectoryIsAbsent(): void {
		$root = dirname( __DIR__, 2 );

		$this->assertDirectoryDoesNotExist( $root . '/templates' );

		$container = $this->load( array( 'paths' => array( __DIR__ => null ) ), $root );

		$this->assertSame(
			array( array( 'addPath', array( __DIR__, FilesystemLoader::MAIN_NAMESPACE ) ) ),
			$this->addPathCalls( $container )
		);
	}

	/**
	 * A value of the wrong type is refused while the container builds, and the message
	 * names the key, the type expected and the type given. The description each key
	 * carries in the tree comes out with it, so the reader also gets told what the key
	 * is for.
	 *
	 * @return void
	 */
	public function testAValueOfTheWrongTypeIsRejectedAndNamesTheExpectedType(): void {
		$this->expectException( InvalidTypeException::class );
		$this->expectExceptionMessage(
			'Invalid type for path "twig.strict_variables". Expected "bool", but got "string".'
		);

		$this->process( array( array( 'strict_variables' => 'yes' ) ) );
	}
	/**
	 * The three boolean keys, each written with nothing after it.
	 *
	 * @return array<string, array{string}>
	 */
	public static function nullableBooleanKeys(): array {
		return array(
			'debug'            => array( 'debug' ),
			'strict_variables' => array( 'strict_variables' ),
			'use_yield'        => array( 'use_yield' ),
		);
	}

	/**
	 * A key written with nothing after it is refused, and the message says what to
	 * write instead.
	 *
	 * The library turns a null into the boolean node's "null equivalent", which is
	 * true for every node whose default is not itself null. So "debug:" on its own
	 * line — a key someone started and left — used to switch Twig's debug mode on, in
	 * production, silently. None of the three means true, and guessing which one it
	 * does mean is worse than saying so.
	 *
	 * @param string $key The configuration key written with no value.
	 * @return void
	 */
	#[DataProvider( 'nullableBooleanKeys' )]
	public function testABooleanKeyWrittenWithNoValueIsRefused( string $key ): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage(
			sprintf( 'The "twig.%s" key was written with no value. A null is neither a yes nor a no', $key )
		);

		$this->process( array( array( $key => null ) ) );
	}
	/**
	 * A global Twig would never see, refused at the key rather than as a fatal.
	 *
	 * Every extension configuration is serialised while the container compiles, so a
	 * closure — the value a host reaches for first when a global has to be computed —
	 * ended the build on "Serialization of \'Closure\' is not allowed", a message
	 * carrying neither the key, nor this bundle, nor anything a reader could act on.
	 * The README points at a service or a Twig function for exactly this case, and now
	 * so does the failure.
	 *
	 * @return void
	 */
	public function testAGlobalThatCannotBeSerialisedIsRefused(): void {
		$this->expectException( InvalidConfigurationException::class );
		$this->expectExceptionMessage(
			'Invalid configuration for path "twig.globals.now": a Closure cannot be used here: it cannot be serialised'
		);

		$this->process( array( array( 'globals' => array( 'now' => static fn (): int => 1 ) ) ) );
	}
	/**
	 * One configuration a host could plausibly write, and the fragment of the refusal
	 * that tells them what to write instead.
	 *
	 * @return array<string, array{array<string, mixed>, string}>
	 */
	public static function valuesTwigCannotUse(): array {
		return array(
			'paths as a list'                       => array( array( 'paths' => array( '/abs/templates' ) ), 'a map of directory to namespace, not a list of directories' ),
			'globals as a list'                     => array( array( 'globals' => array( 'Jérôme' ) ), 'a map of name to value, not a list of values' ),
			'an empty charset'                      => array( array( 'charset' => '' ), 'cannot contain an empty value' ),
			'an empty date format'                  => array( array( 'date' => array( 'format' => '' ) ), 'cannot contain an empty value' ),
			'an optimizer mode Twig refuses'        => array( array( 'optimizations' => 999 ), 'twig.optimizations' ),
			'an extension that is not a class name' => array( array( 'extensions' => array( 123 ) ), 'an extension is named by its class' ),
			'a timezone that is not a string'       => array( array( 'date' => array( 'timezone' => false ) ), 'a timezone is an identifier such as "Europe/Paris"' ),
		);
	}

	/**
	 * Every one of these was accepted by the tree and failed later — as a type error
	 * inside this bundle, as an exception from inside Twig at the first render, or not
	 * at all: a list of globals produced variables named "0" and "1" that no template
	 * could read, and said nothing.
	 *
	 * A configuration mistake belongs to the build, where the key and the value are
	 * both still in hand.
	 *
	 * @param array<string, mixed> $config   The configuration a host would write.
	 * @param string               $expected The fragment of the refusal that has to appear.
	 * @return void
	 */
	#[DataProvider( 'valuesTwigCannotUse' )]
	public function testAValueTwigCannotUseIsRefusedWhileTheContainerBuilds( array $config, string $expected ): void {
		$this->expectException( InvalidConfigurationException::class );
		$this->expectExceptionMessage( $expected );

		$this->process( array( $config ) );
	}
	/**
	 * The date and number settings live on the environment's core extension, out of
	 * reach of a method call on the definition, so they travel through a configurator
	 * — which the container runs after every method call it stacked. Nothing in the
	 * suite said so, and a host writing its own configurator on that definition
	 * replaces this one outright rather than adding to it, which would drop every
	 * date and number setting without a word.
	 *
	 * @return void
	 */
	public function testTheEnvironmentIsConfiguredByTheCoreSettings(): void {
		$container = $this->load( array(), '/srv/site' );

		$configurator = $container->getDefinition( Environment::class )->getConfigurator();

		$this->assertIsArray( $configurator );
		$this->assertCount( 2, $configurator );
		$this->assertInstanceOf( Reference::class, $configurator[0] );
		$this->assertSame( CoreSettings::class, (string) $configurator[0] );
		$this->assertSame( '__invoke', $configurator[1] );
	}
	/**
	 * A host that lists the default directory in "paths" as well gets one addPath call
	 * for it, not two. Twig would take both and search the same directory twice for
	 * every template it cannot find.
	 *
	 * @return void
	 */
	public function testTheDefaultDirectoryIsNotAppendedTwiceWhenItIsAlsoListed(): void {
		$root      = dirname( __DIR__ ) . '/Fixtures/Project';
		$container = $this->load( array( 'paths' => array( $root . '/templates' => null ) ), $root );

		$paths = array();

		foreach ( $this->addPathCalls( $container ) as $call ) {
			$this->assertIsArray( $call );
			$paths[] = is_array( $call[1] ?? null ) ? $call[1][0] : null;
		}

		$this->assertSame( array( $root . '/templates' ), $paths );
	}

	/**
	 * And a trailing separator is not a second directory. Twig trims one off as it
	 * registers a path, so the two spellings were one directory to Twig and two to a
	 * comparison of the strings.
	 *
	 * @return void
	 */
	public function testATrailingSeparatorDoesNotMakeTheDefaultDirectoryASecondPath(): void {
		$root      = dirname( __DIR__ ) . '/Fixtures/Project';
		$container = $this->load( array( 'paths' => array( $root . '/templates/' => null ) ), $root );

		$this->assertCount( 1, $this->addPathCalls( $container ) );
	}

	/**
	 * A Windows path is absolute, and the rule this bundle applies is the one the
	 * filesystem loader applies. A drive letter has to reach the "does not exist"
	 * branch rather than be called relative.
	 *
	 * @return void
	 */
	public function testADriveLetterCountsAsAnAbsolutePath(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'The "twig.paths" directory "C:\\nowhere" does not exist.' );

		$this->load( array( 'paths' => array( 'C:\\nowhere' => null ) ), '/srv/site' );
	}
	/**
	 * An empty default directory is refused rather than turned into the project root
	 * by a concatenation somewhere downstream.
	 *
	 * @return void
	 */
	public function testAnEmptyDefaultPathIsRefused(): void {
		$this->expectException( InvalidConfigurationException::class );
		$this->expectExceptionMessage( 'The path "twig.default_path" cannot contain an empty value' );

		$this->process( array( array( 'default_path' => '' ) ) );
	}
	/**
	 * The default directory is a string, and a number is refused rather than reaching
	 * a private method typed string and dying there as a TypeError.
	 *
	 * The refusal is a normalisation rather than a validation, so it arrives without
	 * the component's own path prefix and names the key itself. That is not a style
	 * choice: a key carrying cannotBeEmpty() together with any final validation closure
	 * refuses an environment variable outright, and this one has to accept both.
	 *
	 * @return void
	 */
	public function testADefaultPathThatIsNotAStringIsRefused(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'The "twig.default_path" key is a directory, so it has to be a string.' );

		$this->process( array( array( 'default_path' => 123 ) ) );
	}
}
