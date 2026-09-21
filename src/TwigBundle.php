<?php
/**
 * OffsetWP Twig Bundle
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle;

use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\ExtensionPass;
use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\LoaderPass;
use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\OwnershipPass;
use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\RuntimePass;
use OffsetWP\Bundle\TwigBundle\Environment\CoreSettings;
use OffsetWP\Bundle\TwigBundle\Loader\NoTemplateSourceLoader;
use OffsetWP\Framework\Bundle\Bundle;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Attribute\AsTwigTest;
use Twig\Environment;
use Twig\Extension\ExtensionInterface;
use Twig\Extension\RuntimeExtensionInterface;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\TokenParser\TokenParserInterface;

/**
 * TwigBundle
 *
 * Makes Twig available to the host as a container service: it builds the environment
 * from the host configuration, collects the extensions, filters, functions, tests,
 * tags, globals and loaders the host provides, and hands the result to the container.
 *
 * The bundle name is the short class name, so the configuration root key and the
 * extension alias are both derived from it and read "twig".
 *
 * @phpstan-type TwigConfiguration array{paths: array<string, string|null>, default_path: string, debug: bool|string, charset: string, strict_variables: bool, autoescape: mixed, cache: mixed, auto_reload: bool|null, optimizations: int, use_yield: bool, globals: array<string, mixed>, extensions: array<int, string>, date: array{format: string, interval_format: string, timezone: string|null}, number_format: array{decimals: int, decimal_point: string, thousands_separator: string}}
 */
final class TwigBundle extends Bundle {

	/**
	 * The configuration key everything of this bundle lives under.
	 *
	 * The framework derives it from the bundle's short class name, so this constant is
	 * a copy of a derivation rather than its source — and a test asserts the two still
	 * agree, because a configuration written under a key nobody reads is silent.
	 *
	 * @var string
	 */
	public const ALIAS = 'twig';

	/**
	 * The directory searched when the host configures no template path.
	 *
	 * Written as a container parameter expression rather than as a resolved path,
	 * because the configuration tree is built long before any container exists.
	 * It stays unresolved all the way to loadExtension(), which resolves it: the
	 * library resolves the configuration a host wrote, but a default coming from
	 * the tree is applied afterwards and is never seen by that resolution.
	 *
	 * @var string
	 */
	public const DEFAULT_PATH = '%kernel.root_path%/templates';

	/**
	 * Hands the container to the facade, and does nothing else.
	 *
	 * Nothing is built here. What the facade records is the container, so the
	 * environment is still only constructed when something actually asks for it.
	 *
	 * @throws \LogicException When the bundle was booted without a container.
	 * @return void
	 */
	public function boot(): void {
		if ( ! isset( $this->container ) ) {
			throw new \LogicException(
				'Cannot boot the Twig bundle without a container. A bundle receives one from the kernel that registered it, so boot the kernel rather than the bundle.'
			);
		}

		Twig::register( $this->container );
	}

	/**
	 * Registers this bundle's compiler passes.
	 *
	 * They go in the before-optimization phase at the default priority, which puts
	 * them after the library's own tag resolution: by the time they read a tag, the
	 * classes are resolved and everything autoconfiguration had to add has been added.
	 *
	 * @param ContainerBuilder $container The service container.
	 * @return void
	 */
	public function build( ContainerBuilder $container ): void {
		parent::build( $container );

		$container->registerForAutoconfiguration( ExtensionInterface::class )
			->addTag( ExtensionPass::EXTENSION_TAG );

		/*
		 * The runtime tag stays hand-placeable on purpose. Two of the twig/*-extra
		 * packages ship a runtime that implements nothing, so autoconfiguration alone
		 * would silently miss both.
		 */
		$container->registerForAutoconfiguration( RuntimeExtensionInterface::class )
			->addTag( RuntimePass::RUNTIME_TAG );

		$container->registerForAutoconfiguration( TokenParserInterface::class )
			->addTag( ExtensionPass::TOKEN_PARSER_TAG );

		$container->registerForAutoconfiguration( NodeVisitorInterface::class )
			->addTag( ExtensionPass::NODE_VISITOR_TAG );

		$container->registerForAutoconfiguration( LoaderInterface::class )
			->addTag( LoaderPass::LOADER_TAG );

		/*
		 * A public method carrying one of Twig's three attributes makes its class two
		 * things at once: an extension, through the wrapper Twig ships, and a runtime,
		 * because a non-static attributed method compiles to a call on the runtime
		 * loader. Both tags are needed, and both are applied here rather than written
		 * by a host.
		 */
		foreach ( ExtensionPass::ATTRIBUTES as $attribute ) {
			$container->registerAttributeForAutoconfiguration( $attribute, $this->tagAttributedClass( ... ) );
		}

		$container->addCompilerPass( new OwnershipPass() );
		$container->addCompilerPass( new LoaderPass() );
		$container->addCompilerPass( new ExtensionPass() );
		$container->addCompilerPass( new RuntimePass() );
	}

	/**
	 * Tags a class one of Twig's attributes was found on.
	 *
	 * The attribute itself is never read here: the wrapper Twig ships reads every
	 * attribute of the class later, from the class name alone, and does it only when
	 * something asks the environment for a filter.
	 *
	 * The third argument is typed \Reflector and narrowed inside, which is one step
	 * more than it looks. The library reads that type to decide what to scan: naming
	 * \ReflectionMethod there would have it walk methods only, while \Reflector has it
	 * walk public properties and constructor parameters as well, for attributes that
	 * can be declared on neither. Narrowing the type is what one would write — and the
	 * signature the library declares for this callable is \Reflector, so a narrower one
	 * is a static-analysis error rather than a preference. The guard below costs a
	 * comparison and makes the difference unobservable.
	 *
	 * @param ChildDefinition                        $definition The definition of the class carrying the attribute.
	 * @param AsTwigFilter|AsTwigFunction|AsTwigTest $attribute  The attribute that was found, and is not needed.
	 * @param \Reflector                             $reflector  What the attribute was found on.
	 * @return void
	 */
	public function tagAttributedClass( ChildDefinition $definition, AsTwigFilter|AsTwigFunction|AsTwigTest $attribute, \Reflector $reflector ): void {
		unset( $attribute );

		if ( ! $reflector instanceof \ReflectionMethod ) {
			return;
		}

		$definition->addTag( ExtensionPass::ATTRIBUTE_EXTENSION_TAG );
		$definition->addTag( RuntimePass::RUNTIME_TAG );
	}

	/**
	 * Declares the configuration tree.
	 *
	 * @param DefinitionConfigurator $definition The definition configurator.
	 * @return void
	 */
	public function configure( DefinitionConfigurator $definition ): void {
		$definition->import( __DIR__ . '/Resources/config/definition.php' );
	}

	/**
	 * Turns the processed configuration into service definitions.
	 *
	 * @param array<string, mixed>  $config    The processed configuration.
	 * @param ContainerConfigurator $container The container configurator.
	 * @param ContainerBuilder      $builder   The service container.
	 * @phpstan-param TwigConfiguration $config
	 * @return void
	 */
	public function loadExtension( array $config, ContainerConfigurator $container, ContainerBuilder $builder ): void {
		$container->import( __DIR__ . '/Resources/config/services.php' );

		/*
		 * Everything Twig's own constructor accepts, in one argument. A key left out
		 * here is a key Twig fills with its own default, which is what keeps every
		 * default identical to Twig's.
		 */
		$builder->getDefinition( Environment::class )->replaceArgument(
			1,
			array(
				'debug'            => $config['debug'],
				'charset'          => $config['charset'],
				'strict_variables' => $config['strict_variables'],
				'autoescape'       => $config['autoescape'],
				'cache'            => $this->cacheOption( $config['cache'], $builder ),
				'auto_reload'      => $config['auto_reload'],
				'optimizations'    => $config['optimizations'],
				'use_yield'        => $config['use_yield'],
			)
		);

		foreach ( $config['globals'] as $name => $value ) {
			$builder->getDefinition( Environment::class )->addMethodCall(
				'addGlobal',
				array( (string) $name, $this->globalValue( $value ) )
			);
		}

		foreach ( $config['extensions'] as $class ) {
			$this->registerConfiguredExtension( $builder, $class );
		}

		$builder->getDefinition( CoreSettings::class )
			->replaceArgument( 0, $config['date'] )
			->replaceArgument( 1, $config['number_format'] );

		$default_path = $this->defaultPath( $config['default_path'], $builder );
		$loader       = $builder->getDefinition( FilesystemLoader::class );

		foreach ( $this->templatePaths( $config['paths'], $default_path, $builder ) as $arguments ) {
			$loader->addMethodCall( 'addPath', $arguments );
		}

		/*
		 * The stand-in for a project with no template source at all. It is registered
		 * whatever happens and used only when nothing else can be: it is private and
		 * unreferenced otherwise, so the container drops it while it compiles. Which
		 * loader the environment ends up with is decided by the loader pass, the only
		 * place that can see every tagged loader.
		 */
		$builder->setDefinition(
			NoTemplateSourceLoader::class,
			new Definition( NoTemplateSourceLoader::class, array( $default_path ) )
		);
	}

	/**
	 * Turns one configured global into the value the environment receives.
	 *
	 * A value starting with a single "@" names a host service, and "@@" escapes a
	 * literal one. Anything else is passed through as it stands; a container parameter
	 * was already resolved before this bundle ever saw it.
	 *
	 * Whether the service exists is not decided here. The configuration of an
	 * extension is loaded into a container of its own, which holds none of the host's
	 * services, so the question can only be answered once everything has been merged
	 * — which is what the extension pass does.
	 *
	 * @param mixed $value The configured value.
	 * @return mixed
	 */
	private function globalValue( mixed $value ): mixed {
		if ( ! is_string( $value ) || ! str_starts_with( $value, '@' ) ) {
			return $value;
		}

		if ( str_starts_with( $value, '@@' ) ) {
			return substr( $value, 1 );
		}

		return new Reference( substr( $value, 1 ) );
	}

	/**
	 * Registers one class listed under the "extensions" key as a tagged service.
	 *
	 * This is the one-line install path for an extension with no constructor
	 * dependency. Anything needing constructor arguments is declared as an ordinary
	 * service instead, and tagged.
	 *
	 * Autoconfiguration is deliberately off here. A duplicate of the tag this definition
	 * already carries would be harmless — the collector counts a service once however
	 * many times it is tagged — but autoconfiguration applies every rule this bundle
	 * registered, not only that one, so a listed class carrying Twig attributes would
	 * also become a runtime and an attribute extension. The "extensions" key is the
	 * one-line path for a plain extension, and one line is what it stays.
	 *
	 * The id is prefixed rather than being the class name, and that is not cosmetic.
	 * The library captures the host's definitions before it merges an extension's own
	 * and restores them afterwards, so a definition registered under an id the host
	 * already uses is simply discarded — and with it the tag that made the class
	 * reach Twig. A host declaring the class under its own name, which is the form
	 * this package's own recipes teach, would have found this key doing nothing at
	 * all. Prefixed, both definitions survive: either the host tagged theirs and the
	 * duplicate check names both, or they did not and this one works.
	 *
	 * @param ContainerBuilder $builder         The service container.
	 * @param string           $extension_class The class the host listed.
	 * @throws \InvalidArgumentException When the class does not exist or is not an extension.
	 * @return void
	 */
	private function registerConfiguredExtension( ContainerBuilder $builder, string $extension_class ): void {
		if ( ! class_exists( $extension_class ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'The class "%s" listed under "twig.extensions" does not exist. Check the spelling, or install the package that ships it.',
					$extension_class
				)
			);
		}

		if ( ! is_subclass_of( $extension_class, ExtensionInterface::class ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'The class "%s" listed under "twig.extensions" does not implement "%s".',
					$extension_class,
					ExtensionInterface::class
				)
			);
		}

		$builder->setDefinition(
			ExtensionPass::EXTENSION_TAG . '.' . $extension_class,
			( new Definition( $extension_class ) )
				->setAutowired( true )
				->addTag( ExtensionPass::EXTENSION_TAG, array( 'source' => 'twig.extensions' ) )
		);
	}

	/**
	 * Turns the configured cache into the value Twig's constructor accepts.
	 *
	 * Exposed, never acted on: this bundle creates no directory, writes no file and
	 * ships no cache warmer. A value starting with "@" names a host service
	 * implementing Twig's cache interface, and anything else is a directory Twig will
	 * manage itself.
	 *
	 * Unlike a global, there is no "@@" escape here, and there is nothing for one to
	 * do: a path is refused unless it is absolute, and a path beginning with "@" never
	 * is.
	 *
	 * @param mixed            $cache   The configured value.
	 * @param ContainerBuilder $builder The service container.
	 * @throws \InvalidArgumentException When the value is neither false, a path nor a reference.
	 * @return bool|string|Reference
	 */
	private function cacheOption( mixed $cache, ContainerBuilder $builder ): bool|string|Reference {
		if ( false === $cache ) {
			return false;
		}

		if ( ! is_string( $cache ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'The "twig.cache" key expects false, an absolute directory or an "@service_id" reference, %s given.',
					get_debug_type( $cache )
				)
			);
		}

		if ( str_starts_with( $cache, '@' ) ) {
			return new Reference( substr( $cache, 1 ) );
		}

		if ( $this->isEnvPlaceholder( $cache, $builder ) ) {
			return $cache;
		}

		if ( ! $this->isAbsolutePath( $cache ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'The "twig.cache" path "%s" is not an absolute path. Twig resolves relative paths against the current working directory, which is unpredictable under a web server. Use an absolute path, for example "%%kernel.root_path%%/var/cache/twig".',
					$cache
				)
			);
		}

		return $cache;
	}

	/**
	 * Resolves the default template directory, whether or not it exists.
	 *
	 * The default is a container parameter expression and reaches this point
	 * unresolved: the library resolves the configuration a host wrote, but a default
	 * supplied by the tree is applied afterwards and escapes that resolution.
	 *
	 * A missing directory is only tolerated when it is the default this bundle
	 * invented. A host that named a directory itself gets told it is not there.
	 *
	 * Both sides of that comparison are resolved before they meet. The configured
	 * value has already been through the library's own resolution by the time it gets
	 * here, so a host writing the very value the reference table prints as the default
	 * arrives as a real directory and would never equal the parameter expression the
	 * constant holds. Comparing the two unresolved would make writing the default
	 * fail where omitting it boots.
	 *
	 * @param string           $configured The configured default directory, as the tree produced it.
	 * @param ContainerBuilder $builder    The service container.
	 * @throws \InvalidArgumentException When a directory the host named does not exist.
	 * @return string
	 */
	private function defaultPath( string $configured, ContainerBuilder $builder ): string {
		$path    = $this->resolveString( $configured, $builder );
		$default = $this->resolveString( self::DEFAULT_PATH, $builder );

		if ( $this->isEnvPlaceholder( $path, $builder ) ) {
			return $path;
		}

		if ( ! is_dir( $path ) && $default !== $path ) {
			throw new \InvalidArgumentException(
				sprintf(
					'The "twig.default_path" directory "%s" does not exist. Create it, point "twig.default_path" elsewhere, or list your template directories under "twig.paths".',
					$path
				)
			);
		}

		return $path;
	}

	/**
	 * Resolves the container parameters a string holds, and keeps it a string.
	 *
	 * @param string           $value   The value to resolve.
	 * @param ContainerBuilder $builder The service container.
	 * @return string
	 */
	private function resolveString( string $value, ContainerBuilder $builder ): string {
		$resolved = $builder->getParameterBag()->resolveValue( $value );

		return is_string( $resolved ) ? $resolved : $value;
	}

	/**
	 * Lists the template directories in the order the loader must receive them.
	 *
	 * The configured paths come first, in the order the host declared them, and the
	 * default directory comes last, so an explicit path always wins over it. The
	 * default directory is skipped when it does not exist — a project with no templates
	 * yet still boots — and skipped again when the host has already listed it, whatever
	 * trailing separator either spelling carries.
	 *
	 * A default named by an environment variable is appended without asking whether it
	 * exists, because at this point the question has no answer: the container has not
	 * substituted the variable yet, and measuring the placeholder would drop the
	 * directory for good. A path listed under "paths" is already treated that way, for
	 * the same reason and by the same test.
	 *
	 * @param array<string, string|null> $configured   The configured directories, each mapped to a namespace.
	 * @param string                     $default_path The resolved default directory.
	 * @param ContainerBuilder           $builder      The service container.
	 * @throws \InvalidArgumentException When a configured path is relative or missing.
	 * @return list<array{string, string}> One path and namespace pair per directory.
	 */
	private function templatePaths( array $configured, string $default_path, ContainerBuilder $builder ): array {
		$paths = array();

		foreach ( $configured as $path => $namespace ) {
			// PHP coerces a numeric array key to an int, and a directory can be named "2026".
			$path = (string) $path;

			$this->validatePath( $path, $builder );

			$paths[] = array( $path, $namespace ?? FilesystemLoader::MAIN_NAMESPACE );
		}

		if ( ! $this->isEnvPlaceholder( $default_path, $builder ) && ! is_dir( $default_path ) ) {
			return $paths;
		}

		foreach ( $paths as $path ) {
			if ( $this->sameDirectory( $path[0], $default_path ) && FilesystemLoader::MAIN_NAMESPACE === $path[1] ) {
				return $paths;
			}
		}

		$paths[] = array( $default_path, FilesystemLoader::MAIN_NAMESPACE );

		return $paths;
	}

	/**
	 * Whether two configured paths name one directory.
	 *
	 * Twig trims a trailing separator off a path as it registers it, so "/x/templates"
	 * and "/x/templates/" are one directory to it and were two to a comparison of the
	 * strings. The default directory was then appended a second time, and Twig searched
	 * it twice for every template it could not find.
	 *
	 * @param string $path  One configured directory.
	 * @param string $other Another.
	 * @return bool
	 */
	private function sameDirectory( string $path, string $other ): bool {
		return rtrim( $path, '/\\' ) === rtrim( $other, '/\\' );
	}

	/**
	 * Refuses a configured path that Twig could not use, at build time.
	 *
	 * A relative path is refused outright rather than resolved: Twig resolves one
	 * against the current working directory, which under a web server is whatever
	 * the process happened to start in.
	 *
	 * @param string           $path    The configured path.
	 * @param ContainerBuilder $builder The service container.
	 * @throws \InvalidArgumentException When the path is relative or is not a directory.
	 * @return void
	 */
	private function validatePath( string $path, ContainerBuilder $builder ): void {
		if ( $this->isEnvPlaceholder( $path, $builder ) ) {
			return;
		}

		if ( ! $this->isAbsolutePath( $path ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'The "twig.paths" key "%s" is not an absolute path. Twig resolves relative paths against the current working directory, which is unpredictable under a web server. Use an absolute path, for example "%%kernel.root_path%%/templates".',
					$path
				)
			);
		}

		if ( ! is_dir( $path ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'The "twig.paths" directory "%s" does not exist. Create it, or correct the entry in "twig.paths".',
					$path
				)
			);
		}
	}

	/**
	 * Whether a value is still standing in for an environment variable.
	 *
	 * The container substitutes the real value while it compiles, which is long after
	 * this extension is loaded, so at this point the string is an opaque placeholder.
	 * Every filesystem check here would be measuring that placeholder: the directory
	 * an environment variable names was reported as "not an absolute path", and that
	 * is how a perfectly good configuration was refused for the one reason that could
	 * not be true of it.
	 *
	 * Nothing checks the real value later either — nothing can, from here — so a
	 * directory named this way is Twig's own problem at the first render, which is the
	 * same deal a path written directly has when it is deleted afterwards.
	 *
	 * @param string           $value   The configured value.
	 * @param ContainerBuilder $builder The service container.
	 * @return bool
	 */
	private function isEnvPlaceholder( string $value, ContainerBuilder $builder ): bool {
		return $value !== $builder->resolveEnvPlaceholders( $value );
	}

	/**
	 * Whether a path is absolute, by the same rule the filesystem loader applies.
	 *
	 * @param string $path The path to test.
	 * @return bool
	 */
	private function isAbsolutePath( string $path ): bool {
		return str_starts_with( $path, '/' )
			|| str_starts_with( $path, '\\' )
			|| 1 === preg_match( '#^[a-zA-Z]:[\\\\/]#', $path );
	}
}
