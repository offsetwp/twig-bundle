<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Bundle
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Bundle;

use Twig\Loader\LoaderInterface;

/**
 * A service of that bundle that reads templates on its own, looking for a component's
 * template before anything renders it. It has to read them from the source Twig reads
 * from, or it finds templates Twig cannot and misses those Twig can.
 */
final class TemplateFinder {

	/**
	 * Constructor.
	 *
	 * @param LoaderInterface $loader The loader it was handed as "twig.loader".
	 * @return void
	 */
	public function __construct( private LoaderInterface $loader ) {
	}

	/**
	 * The loader it was handed.
	 *
	 * @return LoaderInterface
	 */
	public function loader(): LoaderInterface {
		return $this->loader;
	}
}
