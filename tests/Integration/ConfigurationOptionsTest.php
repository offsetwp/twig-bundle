<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Integration
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Integration;

use OffsetWP\Bundle\TwigBundle\Environment\CoreSettings;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\EscapingStrategy;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\KernelTestCase;
use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\MemoryCache;
use OffsetWP\Bundle\TwigBundle\TwigBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Twig\Error\LoaderError;
use Twig\Cache\FilesystemCache;
use Twig\Extension\EscaperExtension;
use Twig\Error\RuntimeError;
use Twig\Source;

/**
 * One case per configuration key, rendered against a real kernel.
 */
#[CoversClass( TwigBundle::class )]
#[CoversClass( CoreSettings::class )]
final class ConfigurationOptionsTest extends KernelTestCase {

	/**
	 * The value the escaping cases render, chosen so that every strategy shows.
	 *
	 * @var string
	 */
	private const MARKUP = '<b>Jérôme</b>';

	/**
	 * The context the date fixtures render, pinned so that no assertion depends on
	 * the clock or on the machine's own timezone.
	 *
	 * @return array<string, \DateTimeImmutable|\DateInterval>
	 */
	private function moment(): array {
		return array(
			'moment' => new \DateTimeImmutable( '2026-01-01 12:00:00', new \DateTimeZone( 'UTC' ) ),
			'span'   => new \DateInterval( 'P3D' ),
		);
	}

	/**
	 * Render a date fixture through a kernel with the given date settings.
	 *
	 * @param array<string, string|null> $date     The date settings a host would write.
	 * @param string                     $template The fixture of this case.
	 * @return string
	 */
	private function renderDate( array $date, string $template ): string {
		return $this->twig( $this->boot( array( 'date' => $date ) ) )->render( $template, $this->moment() );
	}

	/**
	 * Render the escaping fixture of one case.
	 *
	 * Every escaping case has a template of its own, and that is not decoration.
	 * Twig names a compiled template after its cache key and its options hash, and
	 * the escaping strategy is in neither — so two environments differing only in
	 * that strategy would share one compiled class, and whichever case ran first
	 * would decide the answer for the others.
	 *
	 * @param array<string, mixed> $twig_config The configuration a host would write.
	 * @param string               $template    The fixture of this case.
	 * @return string
	 */
	private function escape( array $twig_config, string $template ): string {
		return $this->twig( $this->boot( $twig_config ) )->render( $template, array( 'value' => self::MARKUP ) );
	}

	/**
	 * Render a template through a kernel configured with the given paths.
	 *
	 * @param array<string, string|null> $paths    The configured template directories.
	 * @param string                     $template The template to render.
	 * @return string
	 */
	private function render( array $paths, string $template ): string {
		return $this->twig( $this->boot( array( 'paths' => $paths ) ) )
			->render( $template, array( 'name' => 'Jérôme' ) );
	}

	/**
	 * A namespace is how a shared directory, or a bundle, contributes templates
	 * without colliding with the project's own.
	 *
	 * @return void
	 */
	public function testANamespacedTemplateResolves(): void {
		$this->assertSame(
			"Welcome, Jérôme.\n",
			$this->render( array( $this->project() . '/templates/emails' => 'emails' ), '@emails/welcome.twig' )
		);
	}

	/**
	 * A directory named by an environment variable. The container substitutes the real
	 * value while it compiles, which is long after this bundle is loaded, so what the
	 * bundle sees is an opaque placeholder — and every filesystem check it ran was
	 * measuring that placeholder. The result was "is not an absolute path" about a path
	 * that was absolute, and a configuration that would have worked was refused for the
	 * one reason that could not be true of it.
	 *
	 * @return void
	 */
	public function testAPathNamedByAnEnvironmentVariableIsLeftToTheContainer(): void {
		$_ENV['TWIG_BUNDLE_TEMPLATES'] = $this->project() . '/templates-a';

		try {
			$this->assertSame(
				"from a\n",
				$this->render( array( '%env(TWIG_BUNDLE_TEMPLATES)%' => null ), 'duplicate.twig' )
			);
		} finally {
			unset( $_ENV['TWIG_BUNDLE_TEMPLATES'] );
		}
	}

	/**
	 * And the default directory, which takes a different route through the bundle and
	 * was the one of the three the README promised without a test behind it.
	 *
	 * Two things refused it. The key carried cannotBeEmpty() and a validation closure,
	 * and a node with both refuses an environment variable outright — the pass that
	 * checks a tree against its placeholders replays the configuration with the empty
	 * string standing in for a scalar, and rather than hand that to a closure the
	 * component ends the build on a message about empty values. Underneath that, the
	 * directory was appended only when it existed, and a placeholder never does.
	 *
	 * @return void
	 */
	public function testTheDefaultPathCanBeNamedByAnEnvironmentVariable(): void {
		$_ENV['TWIG_BUNDLE_DEFAULT_PATH'] = $this->project() . '/templates';

		try {
			$twig = $this->twig(
				$this->boot( array( 'default_path' => '%env(TWIG_BUNDLE_DEFAULT_PATH)%' ), null, 'ProjectWithoutTemplates' )
			);

			$this->assertSame( "Hello, Jérôme!\n", $twig->render( 'hello.twig', array( 'name' => 'Jérôme' ) ) );
		} finally {
			unset( $_ENV['TWIG_BUNDLE_DEFAULT_PATH'] );
		}
	}

	/**
	 * Path order is template precedence. Both orders are asserted, because a test
	 * that only tried one could not tell declaration order from alphabetical order.
	 *
	 * @return void
	 */
	public function testTheFirstDeclaredPathWinsForADuplicateFilename(): void {
		$a = $this->project() . '/templates-a';
		$b = $this->project() . '/templates-b';

		$this->assertSame(
			"from a\n",
			$this->render(
				array(
					$a => null,
					$b => null,
				),
				'duplicate.twig'
			)
		);
		$this->assertSame(
			"from b\n",
			$this->render(
				array(
					$b => null,
					$a => null,
				),
				'duplicate.twig'
			)
		);
	}

	/**
	 * The default directory is a fallback. A project that configures a path holding
	 * the same template must get its own, not the one under templates/.
	 *
	 * @return void
	 */
	public function testAnExplicitPathBeatsTheDefaultPath(): void {
		$this->assertFileExists( $this->project() . '/templates/hello.twig' );

		$this->assertSame(
			"A says hello to Jérôme.\n",
			$this->render( array( $this->project() . '/templates-a' => null ), 'hello.twig' )
		);
	}

	/**
	 * Twig's own loader errors already name the namespace and the defined ones.
	 * Wrapping them would lose that, so they pass through untouched.
	 *
	 * @return void
	 */
	public function testAnUnknownNamespaceRaisesTheLoaderError(): void {
		$this->expectException( LoaderError::class );
		$this->expectExceptionMessage( 'There are no registered paths for namespace "shop".' );

		$this->twig( $this->boot() )->render( '@shop/cart.twig' );
	}

	/**
	 * The reason this is a test rather than a comment: a project booted in debug that
	 * finds Twig swallowing errors is a genuine problem, and this is one of the two
	 * defaults in the package that are not Twig's own.
	 *
	 * @return void
	 */
	public function testDebugFollowsTheKernelWhenTheKernelIsInDebug(): void {
		$kernel = $this->kernel();
		$kernel->setDebug( true );
		$kernel->boot();

		$this->assertTrue( $this->twig( $kernel )->isDebug() );
	}

	/**
	 * The other half: the surprise only ever runs in the safe direction, so a kernel
	 * that is not in debug gets a Twig that is not either.
	 *
	 * @return void
	 */
	public function testDebugFollowsTheKernelWhenTheKernelIsNotInDebug(): void {
		$this->assertFalse( $this->twig( $this->boot() )->isDebug() );
	}

	/**
	 * Following the kernel is a default, not a rule.
	 *
	 * @return void
	 */
	public function testDebugCanBeOverriddenFromConfiguration(): void {
		$this->assertTrue( $this->twig( $this->boot( array( 'debug' => true ) ) )->isDebug() );
	}

	/**
	 * The charset is the same arrangement, and barely a deviation at all: the kernel
	 * and Twig agree on UTF-8, so only a project that changed the kernel's charset
	 * sees any difference.
	 *
	 * @return void
	 */
	public function testCharsetFollowsTheKernel(): void {
		$kernel = $this->kernel();
		$kernel->setCharset( 'ISO-8859-15' );
		$kernel->boot();

		$this->assertSame( 'ISO-8859-15', $this->twig( $kernel )->getCharset() );
	}

	/**
	 * Twig's own default, and the forgiving one: an undefined variable renders as
	 * nothing at all.
	 *
	 * @return void
	 */
	public function testStrictVariablesDefaultsToFalse(): void {
		$twig = $this->twig( $this->boot() );

		$this->assertFalse( $twig->isStrictVariables() );
		$this->assertSame( "Hello, !\n", $twig->render( 'hello.twig' ) );
	}

	/**
	 * Turned on, the failure names the variable, the template and the line, which is
	 * why this bundle passes Twig's errors through instead of wrapping them.
	 *
	 * @return void
	 */
	public function testStrictVariablesRaisesOnAnUndefinedVariable(): void {
		$this->expectException( RuntimeError::class );
		$this->expectExceptionMessage( 'Variable "name" does not exist in "hello.twig" at line 1.' );

		$this->twig( $this->boot( array( 'strict_variables' => true ) ) )->render( 'hello.twig' );
	}

	/**
	 * Twig's own default, and the one that matters: markup coming from a variable is
	 * escaped unless somebody says otherwise.
	 *
	 * @return void
	 */
	public function testAutoescapeDefaultsToHtml(): void {
		$this->assertSame( "&lt;b&gt;Jérôme&lt;/b&gt;\n", $this->escape( array(), 'escape/html.twig' ) );
	}

	/**
	 * Off is a legitimate choice for a project rendering something that is not HTML.
	 *
	 * @return void
	 */
	public function testAutoescapeCanBeDisabled(): void {
		$this->assertSame(
			self::MARKUP . "\n",
			$this->escape( array( 'autoescape' => false ), 'escape/off.twig' )
		);
	}

	/**
	 * Any strategy Twig knows, named as a string.
	 *
	 * @return void
	 */
	public function testAutoescapeAcceptsAStrategyName(): void {
		$this->assertSame(
			'\u003Cb\u003EJ\u00E9r\u00F4me\u003C\/b\u003E' . "\n",
			$this->escape( array( 'autoescape' => 'js' ), 'escape/js.twig' )
		);
	}

	/**
	 * "name" picks the strategy from the template name, which is how one project
	 * renders HTML pages and plain-text mail from the same environment.
	 *
	 * @return void
	 */
	public function testAutoescapeAcceptsTheNameStrategy(): void {
		$config = array( 'autoescape' => 'name' );

		$this->assertSame( "&lt;b&gt;Jérôme&lt;/b&gt;\n", $this->escape( $config, 'escape/by-name.twig' ) );
		$this->assertSame( self::MARKUP . "\n", $this->escape( $config, 'escape/by-name.txt.twig' ) );
	}

	/**
	 * A closure reads better than anything else here and cannot be used: every
	 * extension configuration is serialised while the container compiles, and a
	 * closure cannot be serialised. Refusing it at build time with a message naming
	 * what does work is the whole of what this bundle can do about it.
	 *
	 * @return void
	 */
	public function testAutoescapeRefusesAClosureAndNamesTheWorkingForms(): void {
		$this->expectException( InvalidConfigurationException::class );
		$this->expectExceptionMessageMatches(
			'/^Invalid configuration for path "twig\.autoescape": a closure cannot be used here\..*array callable/s'
		);

		$this->boot(
			array(
				'autoescape' => static fn ( string $name ): string|false
					=> str_ends_with( $name, '.txt.twig' ) ? false : 'html',
			)
		);
	}

	/**
	 * What a host writes instead. An array callable is a real PHP callable, it
	 * survives compilation, and Twig calls it once per template name.
	 *
	 * @return void
	 */
	public function testAutoescapeAcceptsAnArrayCallable(): void {
		$config = array( 'autoescape' => array( EscapingStrategy::class, 'guess' ) );

		$this->assertSame( "&lt;b&gt;Jérôme&lt;/b&gt;\n", $this->escape( $config, 'escape/by-callable.twig' ) );
		$this->assertSame( self::MARKUP . "\n", $this->escape( $config, 'escape/by-callable.txt.twig' ) );
	}

	/**
	 * Twig's own default. This bundle ships no cache system, so off is off.
	 *
	 * @return void
	 */
	public function testCacheDefaultsToFalse(): void {
		$this->assertFalse( $this->twig( $this->boot() )->getCache() );
	}

	/**
	 * A directory is handed to Twig untouched, and Twig manages it from there.
	 *
	 * @return void
	 */
	public function testCacheAcceptsAnAbsolutePath(): void {
		$path = $this->project() . '/var/cache/twig';
		$twig = $this->twig( $this->boot( array( 'cache' => $path ) ) );

		$this->assertSame( $path, $twig->getCache() );
		$this->assertInstanceOf( FilesystemCache::class, $twig->getCache( false ) );
	}

	/**
	 * The same "@service_id" syntax the globals key uses. The host owns the cache, so
	 * the host may implement it however it likes.
	 *
	 * @return void
	 */
	public function testCacheAcceptsAServiceReference(): void {
		// cached.twig belongs to this case alone: a compiled class is loaded once per
		// process, so a render of it anywhere else would leave nothing here to see.

		$kernel = $this->boot( array( 'cache' => '@app.twig_cache' ) );
		$cache  = $kernel->service( 'app.twig_cache' );

		$this->assertInstanceOf( MemoryCache::class, $cache );
		$this->assertSame( $cache, $this->twig( $kernel )->getCache() );

		$this->twig( $kernel )->render( 'cached.twig', array( 'name' => 'Jérôme' ) );

		$written = $cache->written();
		$key     = array_key_first( $written );

		$this->assertIsString( $key );
		$this->assertSame( array( $key ), array_values( array_unique( $cache->loaded() ) ) );
		$this->assertStringContainsString( 'class ' . $key, (string) $written[ $key ] );
	}

	/**
	 * The cache key is exposed, not wired: this bundle creates no directory, writes
	 * no file and ships no cache warmer. Configuring one and building the environment
	 * must therefore leave the disk exactly as it was.
	 *
	 * @return void
	 */
	public function testConfiguringTheCacheCreatesNothingOnDisk(): void {
		$path = $this->project() . '/var/cache/twig';

		$this->assertDirectoryDoesNotExist( $path );

		$this->twig( $this->boot( array( 'cache' => $path ) ) );

		$this->assertDirectoryDoesNotExist( $path );
		$this->assertDirectoryDoesNotExist( $this->project() . '/var' );
	}

	/**
	 * Twig's own behaviour, kept: null means "whatever debug says", so a development
	 * kernel picks up a changed template and a production one does not stat every
	 * file on every request.
	 *
	 * @return void
	 */
	public function testAutoReloadFollowsDebugByDefault(): void {
		$this->assertFalse( $this->twig( $this->boot() )->isAutoReload() );

		$kernel = $this->kernel();
		$kernel->setDebug( true );
		$kernel->boot();

		$this->assertTrue( $this->twig( $kernel )->isAutoReload() );
	}

	/**
	 * Following debug is a default, not a rule: a project can keep the diagnostics
	 * and drop the per-request stat of every template.
	 *
	 * @return void
	 */
	public function testAutoReloadCanBeForcedOffWhileDebugIsOn(): void {
		$kernel = $this->kernel( array( 'auto_reload' => false ) );
		$kernel->setDebug( true );
		$kernel->boot();

		$twig = $this->twig( $kernel );

		$this->assertTrue( $twig->isDebug() );
		$this->assertFalse( $twig->isAutoReload() );
	}

	/**
	 * Twig's own default is -1, every optimisation on. There is no getter for it, so
	 * the assertion is on what the compiler produces: the default compiles to the
	 * same code as an explicit -1, and to different code from an explicit 0.
	 *
	 * @return void
	 */
	public function testOptimizationsDefaultToAllEnabled(): void {
		$source = new Source( '{% for item in items %}{{ loop.index }}{% endfor %}', 'optimizations/on.twig' );

		$compiled = $this->twig( $this->boot() )->compileSource( $source );

		$this->assertSame( $compiled, $this->twig( $this->boot( array( 'optimizations' => -1 ) ) )->compileSource( $source ) );
		$this->assertNotSame( $compiled, $this->twig( $this->boot( array( 'optimizations' => 0 ) ) )->compileSource( $source ) );
	}

	/**
	 * Turning them all off changes the compiled code and must change nothing else.
	 * Each case renders a fixture of its own, for the reason given on escape().
	 *
	 * @return void
	 */
	public function testTemplatesStillRenderWithOptimizationsDisabled(): void {
		$context = array( 'items' => array( 'a', 'b' ) );

		$this->assertSame(
			'1:a;2:b;',
			$this->twig( $this->boot() )->render( 'optimizations/on.twig', $context )
		);
		$this->assertSame(
			'1:a;2:b;',
			$this->twig( $this->boot( array( 'optimizations' => 0 ) ) )->render( 'optimizations/off.twig', $context )
		);
	}

	/**
	 * Twig's own default. Turning it on is a decision about custom nodes, not about
	 * rendering, so it is not made on a project's behalf.
	 *
	 * @return void
	 */
	public function testUseYieldDefaultsToFalse(): void {
		$this->assertFalse( $this->twig( $this->boot() )->useYield() );
	}

	/**
	 * Everything Twig itself ships is ready for it, so nothing a project renders
	 * through the stock tags changes. Only a custom node would.
	 *
	 * @return void
	 */
	public function testTemplatesRenderIdenticallyWithUseYieldEnabled(): void {
		$twig = $this->twig( $this->boot( array( 'use_yield' => true ) ) );

		$this->assertTrue( $twig->useYield() );
		$this->assertSame( "Hello, Jérôme!\n", $twig->render( 'hello.twig', array( 'name' => 'Jérôme' ) ) );
		$this->assertSame(
			'1:a;2:b;',
			$twig->render( 'optimizations/on.twig', array( 'items' => array( 'a', 'b' ) ) )
		);
	}

	/**
	 * Both halves of Twig's own default, read off the core extension's properties
	 * rather than remembered. The interval format also proves that a value holding a
	 * per-cent sign reaches Twig intact, which the container could plausibly have
	 * eaten on its way through.
	 *
	 * @return void
	 */
	public function testTheDateFormatDefaultMatchesTwig(): void {
		$twig = $this->twig( $this->boot() );

		$this->assertSame( "January 1, 2026 12:00\n", $twig->render( 'date/format.twig', $this->moment() ) );
		$this->assertSame( "3 days\n", $twig->render( 'date/interval.twig', $this->moment() ) );
	}

	/**
	 * The format a project sets once instead of repeating it at every call site.
	 *
	 * @return void
	 */
	public function testTheDateFormatCanBeConfigured(): void {
		$this->assertSame(
			"01/01/2026 12:00\n",
			$this->renderDate( array( 'format' => 'd/m/Y H:i' ), 'date/format.twig' )
		);
	}

	/**
	 * The other half, which Twig keeps separate because an interval is not a date.
	 *
	 * @return void
	 */
	public function testTheDateIntervalFormatCanBeConfigured(): void {
		$this->assertSame(
			"3 jours\n",
			$this->renderDate( array( 'interval_format' => '%d jours' ), 'date/interval.twig' )
		);
	}

	/**
	 * The fixture is noon UTC, and Paris is an hour ahead of that in January.
	 *
	 * @return void
	 */
	public function testTheTimezoneCanBeConfigured(): void {
		$this->assertSame(
			"13:00\n",
			$this->renderDate( array( 'timezone' => 'Europe/Paris' ), 'date/timezone.twig' )
		);
	}

	/**
	 * A timezone typo would otherwise surface as an exception from inside a template,
	 * on whichever page happened to print a date first.
	 *
	 * @return void
	 */
	public function testAnInvalidTimezoneIsRefusedAndNamesTheKey(): void {
		$this->expectException( InvalidConfigurationException::class );
		$this->expectExceptionMessage(
			'Invalid configuration for path "twig.date.timezone": the timezone "Mars/Olympus" is not one PHP knows.'
		);

		$this->boot( array( 'date' => array( 'timezone' => 'Mars/Olympus' ) ) );
	}

	/**
	 * Twig's own defaults: no decimals, a dot, and a comma. Sensible for an English
	 * site and wrong for most of the rest of the world, which is the whole reason
	 * these three keys are exposed at all.
	 *
	 * @return void
	 */
	public function testTheNumberFormatDefaultsMatchTwig(): void {
		$this->assertSame(
			"1,235\n",
			$this->twig( $this->boot() )->render( 'number.twig', array( 'amount' => 1234.5 ) )
		);
	}

	/**
	 * The French case, which is the one this package was written for: a comma for the
	 * decimals and a space for the thousands, set once instead of at every call site.
	 *
	 * @return void
	 */
	public function testTheNumberFormatCanBeConfigured(): void {
		$config = array(
			'number_format' => array(
				'decimals'            => 2,
				'decimal_point'       => ',',
				'thousands_separator' => ' ',
			),
		);

		$this->assertSame(
			"1 234,50\n",
			$this->twig( $this->boot( $config ) )->render( 'number.twig', array( 'amount' => 1234.5 ) )
		);
	}

	/**
	 * Each option has a test of its own above; this one sets them all at once and
	 * reads every one of them back off the environment. It is the assertion that
	 * catches a key wired to the wrong option, which no single-key test can see.
	 *
	 * @return void
	 */
	public function testEveryScalarOptionRoundTripsToTheEnvironment(): void {
		$config = array(
			'debug'            => true,
			'charset'          => 'ISO-8859-15',
			'strict_variables' => true,
			'autoescape'       => 'js',
			'cache'            => $this->project() . '/var/cache/twig',
			'auto_reload'      => false,
			'optimizations'    => 0,
			'use_yield'        => true,
		);

		$twig = $this->twig( $this->boot( $config ) );

		$this->assertTrue( $twig->isDebug() );
		$this->assertSame( 'ISO-8859-15', $twig->getCharset() );
		$this->assertTrue( $twig->isStrictVariables() );
		$this->assertSame( 'js', $twig->getExtension( EscaperExtension::class )->getDefaultStrategy( 'page.twig' ) );
		$this->assertSame( $config['cache'], $twig->getCache() );
		$this->assertFalse( $twig->isAutoReload() );
		$this->assertTrue( $twig->useYield() );
	}

	/**
	 * Twig calls whatever is neither false nor a string, so a value it cannot call
	 * reaches call_user_func() and fails there — with a message about call_user_func
	 * and nothing about Twig, the key, or what would have worked.
	 *
	 * @return void
	 */
	public function testAutoescapeRefusesAValueTwigCouldNotCall(): void {
		$this->expectException( InvalidConfigurationException::class );
		$this->expectExceptionMessage(
			'Invalid configuration for path "twig.autoescape": the value must be false, an escaping strategy name such as "html", "name", or a callable, and bool is none of those.'
		);

		$this->boot( array( 'autoescape' => true ) );
	}
	/**
	 * Writing "default_path" at the very value the reference table prints as its
	 * default has to behave like omitting it. A project with no templates directory
	 * yet boots either way.
	 *
	 * It did not: the configured value arrives resolved to a real directory and the
	 * constant it was compared against still held the parameter expression, so the two
	 * never matched and the missing directory was reported as one the host had named.
	 *
	 * @return void
	 */
	public function testWritingTheDefaultPathAtItsOwnDefaultBootsLikeOmittingIt(): void {
		$twig = $this->twig(
			$this->boot( array( 'default_path' => TwigBundle::DEFAULT_PATH ), null, 'ProjectWithoutTemplates' )
		);

		$this->expectException( LoaderError::class );

		$twig->render( 'anything.twig' );
	}
	/**
	 * A configured date format and a configured global, on one environment, rendered
	 * together.
	 *
	 * They arrive by two different routes — the global is a method call stacked on the
	 * environment definition, the date format is applied by a configurator — and the
	 * container runs the configurator after every method call. Each route had its own
	 * tests and nothing said the two survive each other, which is the only question
	 * worth asking about an ordering.
	 *
	 * @return void
	 */
	public function testAConfiguredFormatAndAConfiguredGlobalBothSurvive(): void {
		$twig = $this->twig(
			$this->boot(
				array(
					'date'    => array( 'format' => 'Y-m-d' ),
					'globals' => array( 'site' => 'Étoile Malraux' ),
				)
			)
		);

		$this->assertSame( "Étoile Malraux — 2026-01-01\n", $twig->render( 'date/format-and-global.twig', $this->moment() ) );
	}
	/**
	 * The container reads "%name%" as one of its own parameters, so a doubled percent
	 * sign is how a literal one is written — and an interval format is where that comes
	 * up, since the canonical one is nothing but percent signs.
	 *
	 * @return void
	 */
	public function testAnIntervalFormatCanCarryDoubledPercentSigns(): void {
		$twig = $this->twig( $this->boot( array( 'date' => array( 'interval_format' => '%%d days, %%h hours' ) ) ) );

		$this->assertSame( "3 days, 0 hours\n", $twig->render( 'date/interval.twig', $this->moment() ) );
	}
}
