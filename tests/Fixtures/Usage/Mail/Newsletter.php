<?php
/**
 * A fixture project of the OffsetWP Twig Bundle test suite.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Usage\Mail
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Usage\Mail;

use Twig\Environment;

/**
 * A service of the project that renders with Twig and asks for it by its class, which
 * is how autowiring is meant to be used: what it receives is decided by the container.
 *
 * It lives outside the App namespace the other project classes share, on purpose. One
 * fixture project loads that namespace whole, and boots it once as a kernel carrying no
 * bundle at all — where this class would have no environment to be autowired with.
 */
final class Newsletter {

	/**
	 * Constructor.
	 *
	 * @param Environment $twig The environment autowiring hands over.
	 * @return void
	 */
	public function __construct( private Environment $twig ) {
	}

	/**
	 * The environment this service was handed.
	 *
	 * @return Environment
	 */
	public function environment(): Environment {
		return $this->twig;
	}
}
