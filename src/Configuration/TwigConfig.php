<?php
/**
 * OffsetWP Twig Bundle
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Configuration
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Configuration;

use OffsetWP\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * The configuration of this bundle, written as method calls instead of array keys.
 *
 * The array form is the native one and stays supported — it is the only form a YAML
 * file has. What it does not have is a way of telling you what you may write: the
 * eighteen keys, the direction of the arrow in "paths", the strategies "autoescape"
 * accepts, the "@" that names a service and the "@@" that escapes it, all of it lives
 * in the documentation and nowhere an editor can reach. Every one of them is a method
 * here, so the surface completes itself and a typo is a compile error.
 *
 * Two rules hold this together.
 *
 * It invents no default. Only the keys somebody called reach the array, and the
 * configuration tree applies the rest — otherwise every default would exist in two
 * places and the two would drift.
 *
 * It validates nothing. What it produces goes through the same tree as a hand-written
 * array, so a relative path, a missing class or a global that cannot be serialised each
 * raise exactly the message they already raise. One place refuses, and it is not here.
 *
 * Note for anyone editing this file: the namespace sits under the bundle's, so a
 * partially qualified name such as Twig\Environment would resolve to a class beneath it
 * that does not exist. Everything is imported, always.
 */
final class TwigConfig {

	/**
	 * The keys called so far, in the order they were called.
	 *
	 * @var array<string, mixed>
	 */
	private array $config = array();

	/**
	 * Start a configuration.
	 *
	 * @return self
	 */
	public static function create(): self {
		return new self();
	}

	/**
	 * Add a template directory, under a namespace or under the main one.
	 *
	 * Call it once per directory. The main namespace is searched in the order written,
	 * and the default directory comes last.
	 *
	 * @param string      $directory      An absolute directory, or one naming a container parameter.
	 * @param string|null $namespace_name The namespace it answers to, or null for the main one.
	 * @return self
	 */
	public function path( string $directory, ?string $namespace_name = null ): self {
		$paths = is_array( $this->config['paths'] ?? null ) ? $this->config['paths'] : array();

		$paths[ $directory ]   = $namespace_name;
		$this->config['paths'] = $paths;

		return $this;
	}

	/**
	 * Move the directory searched when nothing else is configured.
	 *
	 * @param string $directory The directory, absolute or naming a container parameter.
	 * @return self
	 */
	public function defaultPath( string $directory ): self {
		$this->config['default_path'] = $directory;

		return $this;
	}

	/**
	 * Whether Twig runs in debug mode. Follows the kernel when never called.
	 *
	 * @param bool $debug Whether to turn it on.
	 * @return self
	 */
	public function debug( bool $debug = true ): self {
		$this->config['debug'] = $debug;

		return $this;
	}

	/**
	 * The charset of the templates. Follows the kernel when never called.
	 *
	 * @param string $charset The charset.
	 * @return self
	 */
	public function charset( string $charset ): self {
		$this->config['charset'] = $charset;

		return $this;
	}

	/**
	 * Whether an undefined variable raises instead of rendering as empty.
	 *
	 * @param bool $strict Whether to turn it on.
	 * @return self
	 */
	public function strictVariables( bool $strict = true ): self {
		$this->config['strict_variables'] = $strict;

		return $this;
	}

	/**
	 * The escaping strategy, named or written out.
	 *
	 * @param Escaping|string $strategy One of Twig's own, or the name of one this project registered.
	 * @return self
	 */
	public function autoescape( Escaping|string $strategy ): self {
		$this->config['autoescape'] = $strategy instanceof Escaping ? $strategy->value : $strategy;

		return $this;
	}

	/**
	 * Pick the escaping strategy with a static method of your own.
	 *
	 * Two strings rather than a callable, because the configuration is serialised while
	 * the container compiles and a closure cannot be.
	 *
	 * @param string $escaper_class  The class holding it.
	 * @param string $escaper_method The static method, taking a template name.
	 * @return self
	 */
	public function autoescapeWith( string $escaper_class, string $escaper_method ): self {
		$this->config['autoescape'] = array( $escaper_class, $escaper_method );

		return $this;
	}

	/**
	 * Escape nothing.
	 *
	 * @return self
	 */
	public function noAutoescape(): self {
		$this->config['autoescape'] = false;

		return $this;
	}

	/**
	 * Write compiled templates into a directory.
	 *
	 * @param string $directory An absolute directory, or one naming a container parameter.
	 * @return self
	 */
	public function cacheDirectory( string $directory ): self {
		$this->config['cache'] = $directory;

		return $this;
	}

	/**
	 * Hand the compilation cache to a service of your own.
	 *
	 * @param string $service_id The id of a service implementing Twig's cache interface.
	 * @return self
	 */
	public function cacheService( string $service_id ): self {
		$this->config['cache'] = '@' . $service_id;

		return $this;
	}

	/**
	 * Compile on every render, which is Twig's own default.
	 *
	 * @return self
	 */
	public function noCache(): self {
		$this->config['cache'] = false;

		return $this;
	}

	/**
	 * Whether Twig recompiles a changed template. Null follows debug.
	 *
	 * @param bool|null $auto_reload Whether to turn it on, or null to follow debug.
	 * @return self
	 */
	public function autoReload( ?bool $auto_reload = true ): self {
		$this->config['auto_reload'] = $auto_reload;

		return $this;
	}

	/**
	 * Twig's compiler optimisations: -1 turns all of them on, 0 turns all of them off.
	 *
	 * @param int $optimizations The bitmask.
	 * @return self
	 */
	public function optimizations( int $optimizations ): self {
		$this->config['optimizations'] = $optimizations;

		return $this;
	}

	/**
	 * Whether compiled templates yield their output instead of building it in memory.
	 *
	 * @param bool $use_yield Whether to turn it on.
	 * @return self
	 */
	public function useYield( bool $use_yield = true ): self {
		$this->config['use_yield'] = $use_yield;

		return $this;
	}

	/**
	 * A variable every template can read.
	 *
	 * @param string $name  The name templates read it under.
	 * @param mixed  $value Anything the container can serialise.
	 * @return self
	 */
	public function global( string $name, mixed $value ): self {
		$globals = is_array( $this->config['globals'] ?? null ) ? $this->config['globals'] : array();

		$globals[ $name ]        = $value;
		$this->config['globals'] = $globals;

		return $this;
	}

	/**
	 * A global holding one of your services.
	 *
	 * The service is built when the environment is, on every request that renders
	 * anything at all, because Twig wants the value before its extensions initialise.
	 *
	 * @param string $name       The name templates read it under.
	 * @param string $service_id The id of the service.
	 * @return self
	 */
	public function globalService( string $name, string $service_id ): self {
		return $this->global( $name, '@' . $service_id );
	}

	/**
	 * A global holding a string, whatever it starts with.
	 *
	 * @param string $name  The name templates read it under.
	 * @param string $value The string, taken as it is even when it starts with "@".
	 * @return self
	 */
	public function globalLiteral( string $name, string $value ): self {
		return $this->global( $name, str_starts_with( $value, '@' ) ? '@' . $value : $value );
	}

	/**
	 * Install an extension class that takes no constructor argument.
	 *
	 * @param string $extension_class The class.
	 * @phpstan-param class-string $extension_class
	 * @return self
	 */
	public function extension( string $extension_class ): self {
		$extensions = is_array( $this->config['extensions'] ?? null ) ? $this->config['extensions'] : array();

		$extensions[]               = $extension_class;
		$this->config['extensions'] = array_values( $extensions );

		return $this;
	}

	/**
	 * Install several of them.
	 *
	 * @param string ...$extension_classes The classes.
	 * @phpstan-param class-string ...$extension_classes
	 * @return self
	 */
	public function extensions( string ...$extension_classes ): self {
		foreach ( $extension_classes as $extension_class ) {
			$this->extension( $extension_class );
		}

		return $this;
	}

	/**
	 * The format the date filter uses when it is given none.
	 *
	 * @param string $format The format.
	 * @return self
	 */
	public function dateFormat( string $format ): self {
		return $this->nested( 'date', 'format', $format );
	}

	/**
	 * The format the date filter uses for an interval.
	 *
	 * A literal percent sign is written twice: the container reads "%name%" as one of
	 * its own parameters.
	 *
	 * @param string $format The format.
	 * @return self
	 */
	public function dateIntervalFormat( string $format ): self {
		return $this->nested( 'date', 'interval_format', $format );
	}

	/**
	 * The timezone the date filter uses. Null follows PHP's own.
	 *
	 * @param string|null $timezone An identifier such as "Europe/Paris".
	 * @return self
	 */
	public function dateTimezone( ?string $timezone ): self {
		return $this->nested( 'date', 'timezone', $timezone );
	}

	/**
	 * The number of decimals the number_format filter uses when it is given none.
	 *
	 * @param int $decimals How many.
	 * @return self
	 */
	public function numberDecimals( int $decimals ): self {
		return $this->nested( 'number_format', 'decimals', $decimals );
	}

	/**
	 * The decimal separator the number_format filter uses.
	 *
	 * @param string $decimal_point The separator.
	 * @return self
	 */
	public function numberDecimalPoint( string $decimal_point ): self {
		return $this->nested( 'number_format', 'decimal_point', $decimal_point );
	}

	/**
	 * The thousands separator the number_format filter uses.
	 *
	 * @param string $thousands_separator The separator.
	 * @return self
	 */
	public function numberThousandsSeparator( string $thousands_separator ): self {
		return $this->nested( 'number_format', 'thousands_separator', $thousands_separator );
	}

	/**
	 * The configuration, as the array the extension reads.
	 *
	 * Only what was called is in it.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return $this->config;
	}

	/**
	 * Hand the configuration to the container.
	 *
	 * @param ContainerConfigurator $container The container configurator.
	 * @param bool                  $prepend  Whether to prepend it, which is what a bundle contributing templates does.
	 * @return void
	 */
	public function apply( ContainerConfigurator $container, bool $prepend = false ): void {
		$container->extension( TwigBundle::ALIAS, $this->toArray(), $prepend );
	}

	/**
	 * Write one key of a two-level section, creating the section on the way.
	 *
	 * @param string $section The section, "date" or "number_format".
	 * @param string $key     The key inside it.
	 * @param mixed  $value   The value.
	 * @return self
	 */
	private function nested( string $section, string $key, mixed $value ): self {
		$values = is_array( $this->config[ $section ] ?? null ) ? $this->config[ $section ] : array();

		$values[ $key ]           = $value;
		$this->config[ $section ] = $values;

		return $this;
	}
}
