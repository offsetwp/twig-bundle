<?php
/**
 * OffsetWP Twig Bundle
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Loader
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Loader;

use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;

/**
 * The loader used when nothing at all provides templates.
 *
 * The environment cannot be built without a loader, and a project that has not
 * created its templates directory yet must still be able to boot. This loader is
 * what stands in: it costs nothing to build and it raises a message naming the
 * directory that was looked for the first time a template is asked for.
 *
 * Without it the failure would be Twig's own "There are no registered paths for
 * namespace" — accurate, and useless to somebody who has never configured this
 * bundle.
 *
 * The first method Twig reaches for is getCacheKey(), not getSourceContext(): it
 * names the compiled class before it reads anything. Every method that has to answer
 * about a source therefore raises the same failure, so that whichever one Twig calls
 * first, the message is the same one.
 */
final class NoTemplateSourceLoader implements LoaderInterface {

	/**
	 * Constructor.
	 *
	 * @param string $default_path The directory that was looked for and is not there.
	 * @return void
	 */
	public function __construct( private string $default_path ) {
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $name The template name.
	 * @throws LoaderError Always: there is no source to read from.
	 * @return never
	 */
	public function getSourceContext( string $name ): never {
		throw $this->noSource();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $name The template name.
	 * @throws LoaderError Always: there is no source to read from.
	 * @return never
	 */
	public function getCacheKey( string $name ): never {
		throw $this->noSource();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $name The template name.
	 * @param int    $time The timestamp the cached template was written at.
	 * @throws LoaderError Always: there is no source to read from.
	 * @return never
	 */
	public function isFresh( string $name, int $time ): never {
		throw $this->noSource();
	}

	/**
	 * {@inheritDoc}
	 *
	 * Yes — ask, and you will be told why there is nothing.
	 *
	 * This is the one method here that could answer honestly, and answering honestly
	 * is what loses the message. A chain skips past any loader whose exists() is
	 * false, so the reader gets "Template is not defined" and never learns that no
	 * template source is configured at all; answering true makes the chain call
	 * getSourceContext(), catch the failure below and fold it into its own report.
	 * Twig's own resolveTemplate() does the same with a list of candidate names.
	 *
	 * Nothing is swallowed by it either: "ignore missing" catches the failure below
	 * exactly as it catches a real one.
	 *
	 * @param string $name The template name.
	 * @return bool
	 */
	public function exists( string $name ): bool {
		return true;
	}

	/**
	 * The failure, built once and thrown from every method that has to read a source.
	 *
	 * @return LoaderError
	 */
	private function noSource(): LoaderError {
		return new LoaderError(
			sprintf(
				'Twig has no template source. The default directory "%s" does not exist and no "twig.paths" entry or "twig.loader" service is configured. Create that directory, or configure "twig.paths".',
				$this->default_path
			)
		);
	}
}
