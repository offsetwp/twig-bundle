<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Usage
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Usage;

use App\Twig\VisitedTemplates;
use OffsetWP\Bundle\TwigBundle\TwigBundle;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Six ways into Twig, none of them written down.
 *
 * The project's config/services.php has one load() over its namespace, autowiring and
 * autoconfiguration on, and not a single tag. Everything below is found because of
 * what the classes are: an attribute on a method, an interface on a class. This is the
 * arrangement the README calls the ordinary one, and no test outside this suite has
 * ever reached it through a real services file.
 */
#[CoversClass( TwigBundle::class )]
final class AutoconfiguredServicesTest extends UsageTestCase {
	/**
	 * Everything a class of attributes declares, in one render: a filter that is not
	 * static and therefore travels through the runtime loader, a static function, and
	 * a static test. Plus the two filters of a full extension, one pointing at itself
	 * and one at a runtime class.
	 *
	 * @return void
	 */
	public function testAttributesAndExtensionsAreFoundWithoutATag(): void {
		$this->assertSame(
			"hello-world-2026|120.00|60.00|25|yes\n",
			$this->twig( $this->boot() )->render( 'site.twig' )
		);
	}

	/**
	 * A tag of the project's own, which is one class implementing one interface.
	 *
	 * @return void
	 */
	public function testATokenParserIsFoundWithoutATag(): void {
		$this->assertSame( 'Bonjour, Jérôme', $this->twig( $this->boot() )->render( 'tag.twig' ) );
	}

	/**
	 * A node visitor, which runs while a template compiles and is asked afterwards
	 * what it saw. The service the container holds is the one Twig received.
	 *
	 * This template is rendered by this case and by nothing else: a compiled class is
	 * built once per process, so a second test rendering it would leave this one
	 * nothing to observe.
	 *
	 * @return void
	 */
	public function testANodeVisitorIsFoundWithoutATag(): void {
		$kernel = $this->boot();

		$this->twig( $kernel )->render( 'visited.twig' );

		$visitor = $kernel->service( VisitedTemplates::class );

		$this->assertInstanceOf( VisitedTemplates::class, $visitor );
		$this->assertContains( 'visited.twig', $visitor->names() );
	}

	/**
	 * A loader of the project's own, chained with the filesystem one rather than
	 * replacing it: a template only it has renders, and so does a template only the
	 * directory has.
	 *
	 * @return void
	 */
	public function testACustomLoaderIsChainedWithTheFilesystemOne(): void {
		$twig = $this->twig( $this->boot() );

		$this->assertSame(
			'The next meeting is on 01/03/2026.',
			$twig->render(
				'announcements/notice.twig',
				array( 'meeting' => new \DateTimeImmutable( '2026-03-01 09:00:00', new \DateTimeZone( 'Europe/Paris' ) ) )
			)
		);

		$this->assertSame( "Hello, Jérôme!\n", $twig->render( 'hello.twig', array( 'name' => 'Jérôme' ) ) );
	}
}
