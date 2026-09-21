<?php
/**
 * A fixture project of the OffsetWP Twig Bundle test suite.
 *
 * @author Jérôme Wohlschlegel
 * @package App\Twig
 */

declare( strict_types=1 );

namespace App\Twig;

use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * A template source of the project's own, the shape of one reading from a database.
 *
 * Its templates are a constructor argument, so the services file gives it those
 * rather than leaving them to autowiring — which is what a project does with any
 * service that needs data rather than dependencies.
 */
final class AnnouncementLoader implements LoaderInterface {
	/**
	 * Constructor.
	 *
	 * @param array<string, string> $announcements The templates this loader knows, by name.
	 * @return void
	 */
	public function __construct( private array $announcements = array() ) {
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

		return 'announcement:' . $name;
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
		return isset( $this->announcements[ $name ] );
	}

	/**
	 * The source of one template.
	 *
	 * @param string $name The template name.
	 * @throws LoaderError When this loader does not have that template.
	 * @return string
	 */
	private function source( string $name ): string {
		if ( ! isset( $this->announcements[ $name ] ) ) {
			throw new LoaderError( sprintf( 'Template "%s" is not defined.', $name ) );
		}

		return $this->announcements[ $name ];
	}
}
