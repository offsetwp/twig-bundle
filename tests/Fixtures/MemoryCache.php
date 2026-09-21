<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Fixtures;

use Twig\Cache\CacheInterface;

/**
 * A host service implementing Twig's cache interface, kept entirely in memory.
 *
 * It exists to prove that the "cache" key accepts a service reference. It records
 * what Twig asked it for and never loads anything back, which leaves Twig to compile
 * as it always would — a cache that is safe to point a test at.
 */
final class MemoryCache implements CacheInterface {

	/**
	 * The compiled templates Twig handed over, keyed by cache key.
	 *
	 * @var array<string, string>
	 */
	private array $written = array();

	/**
	 * The keys Twig asked to load.
	 *
	 * @var array<int, string>
	 */
	private array $loaded = array();

	/**
	 * {@inheritDoc}
	 *
	 * @param string $name       The template name.
	 * @param string $class_name The compiled class name.
	 * @return string
	 */
	public function generateKey( string $name, string $class_name ): string {
		return $class_name;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key     The cache key.
	 * @param string $content The compiled template.
	 * @return void
	 */
	public function write( string $key, string $content ): void {
		$this->written[ $key ] = $content;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key The cache key.
	 * @return void
	 */
	public function load( string $key ): void {
		$this->loaded[] = $key;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key The cache key.
	 * @return int
	 */
	public function getTimestamp( string $key ): int {
		return isset( $this->written[ $key ] ) ? 1 : 0;
	}

	/**
	 * The compiled templates Twig handed over.
	 *
	 * @return array<string, string>
	 */
	public function written(): array {
		return $this->written;
	}

	/**
	 * The keys Twig asked to load, in order.
	 *
	 * @return array<int, string>
	 */
	public function loaded(): array {
		return $this->loaded;
	}
}
