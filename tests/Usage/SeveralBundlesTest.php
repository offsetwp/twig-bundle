<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Usage
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Usage;

use OffsetWP\Bundle\TwigBundle\TwigBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use Twig\Loader\FilesystemLoader;

/**
 * A project running a second bundle that ships templates of its own.
 *
 * That is the arrangement any package building on this one produces, and the three
 * sources of templates it creates — the one the other bundle prepended, the one the
 * project configured in its own file, and the project's default directory — all have
 * to survive each other. If prepending ever replaced a project's configuration rather
 * than merging with it, every project using both packages would lose its templates at
 * once and in silence.
 */
#[CoversClass( TwigBundle::class )]
final class SeveralBundlesTest extends UsageTestCase {
	/**
	 * All three sources answer.
	 *
	 * @return void
	 */
	public function testThreeSourcesOfTemplatesSurviveEachOther(): void {
		$twig = $this->twig( $this->boot( 'SiteWithABundle' ) );

		$this->assertSame( "from the bundle\n", $twig->render( '@prepended/page.twig' ) );
		$this->assertSame( "from the project\n", $twig->render( '@project/page.twig' ) );
		$this->assertSame( "from the project templates directory\n", $twig->render( 'hello.twig' ) );
	}

	/**
	 * And what the project wrote next to its paths is still there, which is the half
	 * of a merge a path assertion cannot see.
	 *
	 * @return void
	 */
	public function testTheProjectsOwnConfigurationSurvivesThePrepending(): void {
		$this->assertSame( "Éditions Exemple\n", $this->twig( $this->boot( 'SiteWithABundle' ) )->render( 'owner.twig' ) );
	}

	/**
	 * The namespaces read off the loader itself, so that a merge dropping one is
	 * visible rather than inferred from a render that happened to work.
	 *
	 * @return void
	 */
	public function testEveryContributedNamespaceReachesTheLoader(): void {
		$loader = $this->twig( $this->boot( 'SiteWithABundle' ) )->getLoader();

		$this->assertInstanceOf( FilesystemLoader::class, $loader );

		$namespaces = $loader->getNamespaces();

		$this->assertContains( 'prepended', $namespaces );
		$this->assertContains( 'project', $namespaces );
		$this->assertContains( FilesystemLoader::MAIN_NAMESPACE, $namespaces );
	}
}
