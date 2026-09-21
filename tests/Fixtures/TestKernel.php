<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Fixtures;

use OffsetWP\Framework\Kernel;
use OffsetWP\Support\Env;

/**
 * A kernel an integration test can boot.
 *
 * It is built by hand rather than through the fluent builder, because the builder
 * only ever hands back a kernel that has already booted, and several tests need to
 * look at the container before that happens.
 *
 * The environment is the PRODUCTION literal and never comes from Env::type(), which
 * reaches for a function that only exists inside a request of the host platform.
 */
final class TestKernel extends Kernel {

	/**
	 * The raw configuration the "twig" extension was loaded with.
	 *
	 * @var array<array-key, mixed>
	 */
	private array $extension_config = array();

	/**
	 * Build a kernel rooted at a fixture project.
	 *
	 * @param string               $root_path   The fixture project directory.
	 * @param array<string, mixed> $twig_config The configuration a host would write in
	 *                                          config/packages/twig.php, injected here so
	 *                                          that a test needs no file of its own.
	 * @param \Closure|null        $services    Extra service definitions, registered on the
	 *                                          container the way a host's config/services.php
	 *                                          would, so that a test owns its own services.
	 * @return void
	 */
	public function __construct( string $root_path, private array $twig_config = array(), private ?\Closure $services = null ) {
		parent::__construct( $root_path );

		$this->setEnvironment( Env::PRODUCTION );
		$this->setConfigPath( $root_path . DIRECTORY_SEPARATOR . 'config' );
	}

	/**
	 * The raw configuration the "twig" extension was loaded with, captured before the
	 * container was compiled.
	 *
	 * @return array<array-key, mixed>
	 */
	public function twigExtensionConfig(): array {
		return $this->extension_config;
	}

	/**
	 * Adds the injected configuration on top of whatever the fixture project declares,
	 * then records what the extension will be loaded with.
	 *
	 * @return self
	 */
	protected function registerContainerConfiguration(): self {
		parent::registerContainerConfiguration();

		if ( array() !== $this->twig_config ) {
			$this->container?->loadFromExtension( 'twig', $this->twig_config );
		}

		if ( null !== $this->services && null !== $this->container ) {
			( $this->services )( $this->container );
		}

		$this->extension_config = $this->container?->getExtensionConfig( 'twig' ) ?? array();

		return $this;
	}
}
