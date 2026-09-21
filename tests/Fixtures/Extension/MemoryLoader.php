<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension;

use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * A template source of a host's own, the shape of a loader that reads from a database.
 */
final class MemoryLoader implements LoaderInterface {
	/**
	 * Constructor.
	 *
	 * @param array<string, string> $templates The templates this loader knows, by name.
	 * @return void
	 */
	public function __construct( private array $templates = array() ) {
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $name The template name.
	 * @throws LoaderError When this loader does not have that template.
	 * @return Source
	 */
	public function getSourceContext( string $name ): Source {
		return new Source( $this->source( $name ), $name );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $name The template name.
	 * @throws LoaderError When this loader does not have that template.
	 * @return string
	 */
	public function getCacheKey( string $name ): string {
		$this->source( $name );

		return 'memory:' . $name;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $name The template name.
	 * @param int    $time The timestamp the cached template was written at.
	 * @return bool
	 */
	public function isFresh( string $name, int $time ): bool {
		return $this->exists( $name );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $name The template name.
	 * @return bool
	 */
	public function exists( string $name ): bool {
		return isset( $this->templates[ $name ] );
	}

	/**
	 * The source of one template.
	 *
	 * @param string $name The template name.
	 * @throws LoaderError When this loader does not have that template.
	 * @return string
	 */
	private function source( string $name ): string {
		if ( ! isset( $this->templates[ $name ] ) ) {
			throw new LoaderError( sprintf( 'Template "%s" is not defined.', $name ) );
		}

		return $this->templates[ $name ];
	}
}
