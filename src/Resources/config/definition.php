<?php
/**
 * OffsetWP Twig Bundle
 *
 * The configuration tree of this bundle, rooted at "twig".
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle
 */

declare( strict_types=1 );

use OffsetWP\Bundle\TwigBundle\Environment\CoreSettings;
use OffsetWP\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

return static function ( DefinitionConfigurator $definition ): void {
	$definition->rootNode()
		->children()

			/*
			 * An absolute template directory maps to a namespace, or to null for the
			 * main namespace. Key normalisation is off because the keys are
			 * filesystem paths: a directory named "my-templates" would otherwise be
			 * read as "my_templates".
			 */
			->arrayNode( 'paths' )
				->info( 'Absolute template directories, each mapped to a namespace or to null for the main namespace.' )
				->normalizeKeys( false )
				->defaultValue( array() )
				->validate()
					->ifTrue( static fn ( mixed $value ): bool => is_array( $value ) && array() !== $value && array_is_list( $value ) )
					->thenInvalid( 'this key is a map of directory to namespace, not a list of directories. Write each directory as a key, with null for the main namespace: array( \'/abs/templates\' => null ).' )
				->end()
				->scalarPrototype()->end()
			->end()

			/*
			 * The type check below is a normalisation and not a validation, and that is
			 * load-bearing. A node carrying cannotBeEmpty() together with any final
			 * validation closure refuses an environment variable outright: the pass that
			 * checks a tree against its placeholders replays the configuration with a
			 * witness value of the right type — the empty string for a scalar — and
			 * rather than hand an empty value to a closure the component refuses the key,
			 * on a message about empty values that names no variable. A normalisation
			 * closure runs earlier and leaves that list empty, so the key keeps both its
			 * type check and "%env(TEMPLATES_DIR)%".
			 */
			->scalarNode( 'default_path' )
				->info( 'The directory searched when nothing else is configured. Appended last, and only when it exists.' )
				->defaultValue( TwigBundle::DEFAULT_PATH )
				->cannotBeEmpty()
				->beforeNormalization()
					->ifTrue( static fn ( mixed $value ): bool => null !== $value && ! is_string( $value ) )
					->then( static fn (): never => throw new \InvalidArgumentException( 'The "twig.default_path" key is a directory, so it has to be a string.' ) )
				->end()
			->end()

			/*
			 * The two options that do not default to Twig's own value. Both are written
			 * as container parameter expressions and stay unresolved until the container
			 * resolves them in the environment's argument, which is where they land.
			 *
			 * The charset is not really a deviation: the kernel's own default is UTF-8,
			 * the value Twig uses. Debug is a real one, and it is deliberate — a project
			 * booted in debug and finding Twig swallowing errors is a genuine problem,
			 * and the surprise runs in the safe direction.
			 *
			 * The null refusal below is a normalisation closure rather than a
			 * validation, and that is the whole trick. A boolean node whose default is
			 * not itself null substitutes true for a null before it validates anything,
			 * so a key written with nothing after it silently becomes true. A
			 * normalisation closure is the only hook that runs before the substitution.
			 * Every boolean key that does not genuinely accept null carries one;
			 * auto_reload, which does, is declared with defaultNull() and needs none.
			 */
			->booleanNode( 'debug' )
				->info( 'Whether Twig runs in debug mode. Follows the kernel unless set.' )
				->defaultValue( '%kernel.is_debug%' )
				->beforeNormalization()
					->ifNull()
					->then( static fn (): never => throw new \InvalidArgumentException( 'The "twig.debug" key was written with no value. A null is neither a yes nor a no, and it used to be read as true — which here means Twig\'s debug mode, in production. Remove the key to follow the kernel, or write true or false.' ) )
				->end()
			->end()
			->scalarNode( 'charset' )
				->info( 'The charset of the templates. Follows the kernel unless set.' )
				->defaultValue( '%kernel.charset%' )
				->cannotBeEmpty()
			->end()

			->booleanNode( 'strict_variables' )
				->info( 'Whether an undefined variable raises instead of rendering as empty.' )
				->defaultFalse()
				->beforeNormalization()
					->ifNull()
					->then( static fn (): never => throw new \InvalidArgumentException( 'The "twig.strict_variables" key was written with no value. A null is neither a yes nor a no, and it used to be read as true. Remove the key for the default, or write true or false.' ) )
				->end()
			->end()

			->variableNode( 'autoescape' )
				->info( 'false, an escaping strategy name, "name" to pick one per template name, or a callable.' )
				->defaultValue( 'html' )
				->validate()
					->ifTrue( static fn ( mixed $value ): bool => $value instanceof \Closure )
					->thenInvalid( 'a closure cannot be used here. Every extension configuration is serialised while the container compiles, and a closure cannot be serialised. Use false, a strategy name such as "html", "name", or an array callable such as array( MyStrategy::class, \'guess\' ).' )
				->end()

				/*
				 * Anything Twig cannot call. It calls whatever is neither false nor a
				 * string, so a value like true reaches call_user_func() and fails there,
				 * with a message about call_user_func and nothing about Twig or the key.
				 */
				->validate()
					->ifTrue(
						static function ( mixed $value ): bool {
							return false !== $value && ! is_string( $value ) && ! is_callable( $value );
						}
					)
					->then(
						static function ( mixed $value ): never {
							throw new \InvalidArgumentException(
								sprintf(
									'the value must be false, an escaping strategy name such as "html", "name", or a callable, and %s is none of those.',
									get_debug_type( $value )
								)
							);
						}
					)
				->end()
			->end()

			->variableNode( 'cache' )
				->info( 'false, an absolute directory, or "@service_id" naming a host service. Exposed, never acted on.' )
				->defaultFalse()
			->end()

			->booleanNode( 'auto_reload' )
				->info( 'Whether Twig recompiles a changed template. Null, the default, follows debug.' )
				->defaultNull()
			->end()

			/*
			 * Twig reads this as a bitmask and refuses anything above the three bits it
			 * defines — but only when the environment is built, which is the first render
			 * of a request rather than the build of the container. Every other bad value
			 * in this tree is refused while the container compiles, and this one has a
			 * range, so it can be too.
			 */
			->integerNode( 'optimizations' )
				->info( 'Twig\'s compiler optimisations: -1 turns all of them on, 0 turns all of them off.' )
				->defaultValue( -1 )
				->min( -1 )
				->max( 14 )
			->end()

			->booleanNode( 'use_yield' )
				->info( 'Whether compiled templates yield their output instead of building it in memory.' )
				->defaultFalse()
				->beforeNormalization()
					->ifNull()
					->then( static fn (): never => throw new \InvalidArgumentException( 'The "twig.use_yield" key was written with no value. A null is neither a yes nor a no, and it used to be read as true. Remove the key for the default, or write true or false.' ) )
				->end()
			->end()

			/*
			 * A global is whatever the host wants, with one limit that is not this
			 * bundle's: every extension configuration is serialised while the container
			 * compiles, so a value that cannot be serialised ends the build on a message
			 * naming neither the key nor the bundle. Twig's autoescape key is guarded the
			 * same way, and for the same reason.
			 */
			->arrayNode( 'globals' )
				->info( 'Variables every template gets. A value starting with "@" names a service, "@@" escapes a literal one.' )
				->normalizeKeys( false )
				->defaultValue( array() )
				->validate()
					->ifTrue( static fn ( mixed $value ): bool => is_array( $value ) && array() !== $value && array_is_list( $value ) )
					->thenInvalid( 'this key is a map of name to value, not a list of values. Written as a list it produces globals named "0", "1" and so on, which no template can read.' )
				->end()
				->variablePrototype()
					->validate()
						->ifTrue(
							static function ( mixed $value ): bool {
								if ( null === $value || is_scalar( $value ) ) {
									return false;
								}

								try {
									// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- The object-injection hazard is unserialize(). This serialises to find out whether the container will be able to, and throws the result away.
									serialize( $value );
								} catch ( \Throwable ) {
									return true;
								}

								return false;
							}
						)
						->then(
							static function ( mixed $value ): never {
								throw new \InvalidArgumentException(
									sprintf(
										'a %s cannot be used here: it cannot be serialised, and every extension configuration is serialised while the container compiles. Name a service with "@id" instead, or declare a Twig function and call it from the template.',
										get_debug_type( $value )
									)
								);
							}
						)
					->end()
				->end()
			->end()

			->arrayNode( 'extensions' )
				->info( 'Extension classes with no constructor dependency, each registered and tagged for you.' )
				->defaultValue( array() )
				->scalarPrototype()
					->validate()
						->ifTrue( static fn ( mixed $value ): bool => ! is_string( $value ) )
						->thenInvalid( 'an extension is named by its class, so it has to be a string.' )
					->end()
				->end()
			->end()

			/*
			 * Not options of the environment's constructor but settings of its core
			 * extension, which is why they travel to a configurator instead. They are
			 * here because a French site needs a comma and Europe/Paris on day one,
			 * and the alternative is every project reaching into the extension by hand
			 * after boot.
			 */
			->arrayNode( 'date' )
				->addDefaultsIfNotSet()
				->children()
					->scalarNode( 'format' )
						->info( 'The format the date filter uses when it is given none.' )
						->defaultValue( CoreSettings::DEFAULT_DATE_FORMAT )
						->cannotBeEmpty()
					->end()
					->scalarNode( 'interval_format' )
						->info( 'The format the date filter uses for an interval. A literal percent sign is written "%%", since the container reads "%name%" as one of its own parameters.' )
						->defaultValue( CoreSettings::DEFAULT_INTERVAL_FORMAT )
						->cannotBeEmpty()
					->end()
					->scalarNode( 'timezone' )
						->info( 'The timezone the date filter uses. Null, the default, follows PHP.' )
						->defaultNull()
						->validate()
							->ifTrue(
								static function ( mixed $value ): bool {
									if ( null === $value ) {
										return false;
									}

									if ( ! is_string( $value ) ) {
										return true;
									}

									try {
										new \DateTimeZone( $value );
									} catch ( \Exception ) {
										return true;
									}

									return false;
								}
							)
							->then(
								static function ( mixed $value ): never {
									throw new \InvalidArgumentException(
										sprintf(
											is_string( $value )
												? 'the timezone "%s" is not one PHP knows. Use an identifier such as "Europe/Paris", or leave it out to follow PHP\'s own default.'
												: 'a timezone is an identifier such as "Europe/Paris", and %s is not one. Leave the key out to follow PHP\'s own default.',
											is_string( $value ) ? $value : get_debug_type( $value )
										)
									);
								}
							)
						->end()
					->end()
				->end()
			->end()

			->arrayNode( 'number_format' )
				->addDefaultsIfNotSet()
				->children()
					->integerNode( 'decimals' )
						->info( 'The number of decimals the number_format filter uses when it is given none.' )
						->defaultValue( CoreSettings::DEFAULT_DECIMALS )
					->end()
					->scalarNode( 'decimal_point' )
						->info( 'The decimal separator the number_format filter uses.' )
						->defaultValue( CoreSettings::DEFAULT_DECIMAL_POINT )
					->end()
					->scalarNode( 'thousands_separator' )
						->info( 'The thousands separator the number_format filter uses.' )
						->defaultValue( CoreSettings::DEFAULT_THOUSANDS_SEPARATOR )
					->end()
				->end()
			->end()
		->end();
};
