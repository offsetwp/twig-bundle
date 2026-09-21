<?php
/**
 * OffsetWP Twig Bundle
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle;

use OffsetWP\Framework\Kernel;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface as ServiceContainerInterface;
use Twig\Environment;

/**
 * The way into Twig from anywhere: a theme file, a plugin, a mu-plugin.
 *
 * The registry holds a container and never an environment. That is the whole reason
 * this class can exist without costing anything: a kernel registers itself the moment
 * it boots, and Twig is built the first time somebody actually asks for it.
 *
 * The framework's own instance registry is deliberately not used. It refuses a second
 * registration under the same name, and in this platform a mu-plugin and a theme can
 * each boot a kernel in one request — so the ordinary arrangement would be fatal.
 * Here the last kernel to boot simply wins, and Twig::of() is the unambiguous form.
 *
 * Note for anyone editing this file: this class is named Twig and lives in the bundle
 * namespace, so a partially qualified name such as Twig\Environment would resolve to
 * a class under it that does not exist. Every Twig class is imported, always.
 */
final class Twig {

	/**
	 * The container of the most recently booted kernel that registered this bundle.
	 *
	 * @var ContainerInterface|null
	 */
	private static ?ContainerInterface $container = null;

	/**
	 * The environment of the most recently booted kernel.
	 *
	 * An empty registry has three causes and the failure names all three, because from
	 * here they are indistinguishable: nothing has registered a container, and that is
	 * the whole of what this class knows.
	 *
	 * The first is the obvious one, a call made before the kernel booted. The other two
	 * are the ones that cost an evening, because in both the kernel did boot and the
	 * reader has no reason to doubt it: a config/bundles.php that does not enable this
	 * bundle for the environment being booted, and a kernel built with a services file
	 * rather than a config directory, which registers no bundles at all. Neither can be
	 * detected from inside a bundle whose code never runs — so they are listed here,
	 * where somebody is already reading.
	 *
	 * @throws \LogicException When no kernel carrying this bundle has booted.
	 * @return Environment
	 */
	public static function environment(): Environment {
		if ( null === self::$container ) {
			throw new \LogicException(
				sprintf(
					'No kernel carrying the Twig bundle has booted. If yours has, then it is not carrying it: check that config/bundles.php lists %s and enables it for this environment, and that the kernel was built in configuration mode, because one handed a services file instead of a config directory registers no bundles at all. If it has not booted yet, boot it before calling twig() or Twig::render(), or use $kernel->service( \'twig\' ) on a specific kernel.',
					TwigBundle::class
				)
			);
		}

		return self::of( self::$container );
	}

	/**
	 * Render a template through the most recently booted kernel.
	 *
	 * @param string               $name    The template name.
	 * @param array<string, mixed> $context The variables the template reads.
	 * @throws \LogicException When no kernel carrying this bundle has booted.
	 * @return string
	 */
	public static function render( string $name, array $context = array() ): string {
		return self::environment()->render( $name, $context );
	}

	/**
	 * Render a template straight to the output.
	 *
	 * @param string               $name    The template name.
	 * @param array<string, mixed> $context The variables the template reads.
	 * @throws \LogicException When no kernel carrying this bundle has booted.
	 * @return void
	 */
	public static function display( string $name, array $context = array() ): void {
		self::environment()->display( $name, $context );
	}

	/**
	 * The environment of one specific kernel or container.
	 *
	 * This is the unambiguous form, and the one to reach for when more than one kernel
	 * boots in a request.
	 *
	 * @param Kernel|ContainerInterface $source The kernel, or its container.
	 * @throws \LogicException When the kernel has not booted, or carries no Twig service.
	 * @return Environment
	 */
	public static function of( Kernel|ContainerInterface $source ): Environment {
		if ( ! $source instanceof Kernel ) {
			return self::fromContainer( $source, self::rootPathOf( $source ) );
		}

		try {
			$container = $source->container();
		} catch ( \LogicException ) {
			throw new \LogicException(
				sprintf(
					'Cannot retrieve Twig from a non-booted kernel rooted at "%s". Boot that kernel before asking it for Twig.',
					self::canonical( $source->rootPath() )
				)
			);
		}

		return self::fromContainer( $container, self::rootPathOf( $container ) ?? self::canonical( $source->rootPath() ) );
	}

	/**
	 * Whether a shortcut call would find a Twig service.
	 *
	 * Knowing that a kernel has booted is not the same as knowing that the shortcut
	 * works, and this answers the second question: a kernel booted without this bundle
	 * in its config/bundles.php registers a container that has no "twig" service in it.
	 * Asking a container whether it holds a service does not build the service, so the
	 * answer still costs nothing.
	 *
	 * One case it cannot answer is a "twig" service of somebody else's making, since
	 * finding that out means building it. environment() reports that one.
	 *
	 * @return bool
	 */
	public static function booted(): bool {
		return null !== self::$container && self::$container->has( 'twig' );
	}

	/**
	 * Records the container of a kernel that has just booted.
	 *
	 * @internal
	 *
	 * @param ContainerInterface $container The container of the booting kernel.
	 * @return void
	 */
	public static function register( ContainerInterface $container ): void {
		self::$container = $container;
	}

	/**
	 * Forgets every registration, so that a test can start from nothing.
	 *
	 * @internal
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$container = null;
	}

	/**
	 * The environment held by one container.
	 *
	 * @param ContainerInterface $container The container to read from.
	 * @param string|null        $root_path The root path of its kernel, when it has one.
	 * @throws \LogicException When the container carries no Twig service, or a foreign one.
	 * @return Environment
	 */
	private static function fromContainer( ContainerInterface $container, ?string $root_path ): Environment {
		if ( ! $container->has( 'twig' ) ) {
			throw new \LogicException(
				null === $root_path
					? sprintf( 'This container has no "twig" service. Add %s to the config/bundles.php of its kernel.', TwigBundle::class )
					: sprintf( 'The kernel rooted at "%s" has no "twig" service. Add %s to its config/bundles.php.', $root_path, TwigBundle::class )
			);
		}

		$twig = $container->get( 'twig' );

		if ( ! $twig instanceof Environment ) {
			throw new \LogicException(
				sprintf(
					'The "twig" service is a "%s", not a "%s". Something else in this project has defined a service under that id.%s',
					get_debug_type( $twig ),
					Environment::class,
					null === $root_path ? '' : sprintf( ' The kernel is rooted at "%s".', $root_path )
				)
			);
		}

		return $twig;
	}

	/**
	 * A root path written the way the kernel writes it into its own parameters.
	 *
	 * A kernel keeps the string its constructor was given; the parameter it publishes
	 * is that string resolved. Both name the same directory, and a project rooted
	 * behind a symlink or given a trailing slash gets two different spellings of it —
	 * so one failure said one thing and its neighbour said another about one kernel.
	 * Every message here goes through this.
	 *
	 * @param string $root_path The root path as the kernel was given it.
	 * @return string
	 */
	private static function canonical( string $root_path ): string {
		return realpath( $root_path ) ?: $root_path;
	}

	/**
	 * The root path a container reports, when it is the container of a kernel.
	 *
	 * @param ContainerInterface $container The container to read from.
	 * @return string|null
	 */
	private static function rootPathOf( ContainerInterface $container ): ?string {
		if ( ! $container instanceof ServiceContainerInterface || ! $container->hasParameter( 'kernel.root_path' ) ) {
			return null;
		}

		$root_path = $container->getParameter( 'kernel.root_path' );

		return is_string( $root_path ) ? $root_path : null;
	}
}
