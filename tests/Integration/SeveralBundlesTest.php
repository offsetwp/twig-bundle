<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Integration
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Integration;

use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\KernelTestCase;
use OffsetWP\Bundle\TwigBundle\DependencyInjection\Compiler\LoaderPass;
use OffsetWP\Bundle\TwigBundle\TwigBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use Twig\Loader\FilesystemLoader;

/**
 * More than one bundle contributing to the twig extension in one project.
 *
 * A sibling package in this repository already prepends a namespaced path into this
 * extension, and so will anything else that ships templates. If prepending ever
 * overwrote the host's own configuration instead of merging with it, every project
 * running both would lose its templates at once and with no error.
 */
#[CoversClass( TwigBundle::class )]
#[CoversClass( LoaderPass::class )]
final class SeveralBundlesTest extends KernelTestCase {
	/**
	 * Three sources of templates in one environment: the path a second bundle
	 * prepended, the path the project configured, and the project's own default
	 * directory. All three resolve, so nothing overwrote anything.
	 *
	 * @return void
	 */
	public function testASecondBundlePrependingPathsIsMergedNotOverwritten(): void {
		$root = $this->project( 'PrependingProject' );

		$kernel = $this->boot(
			array( 'paths' => array( $root . '/extra-templates' => 'project' ) ),
			null,
			'PrependingProject'
		);

		$twig = $this->twig( $kernel );

		$this->assertSame( "from the bundle\n", $twig->render( '@prepended/page.twig' ) );
		$this->assertSame( "from the project\n", $twig->render( '@project/page.twig' ) );
		$this->assertSame( "from the project templates directory\n", $twig->render( 'hello.twig' ) );
	}

	/**
	 * And the namespaces the environment ends up with, read off the loader, so that a
	 * merge that silently dropped one would be visible rather than inferred.
	 *
	 * @return void
	 */
	public function testEveryContributedNamespaceReachesTheLoader(): void {
		$root = $this->project( 'PrependingProject' );

		$kernel = $this->boot(
			array( 'paths' => array( $root . '/extra-templates' => 'project' ) ),
			null,
			'PrependingProject'
		);

		$loader = $this->twig( $kernel )->getLoader();

		$this->assertInstanceOf( FilesystemLoader::class, $loader );

		$namespaces = $loader->getNamespaces();

		$this->assertContains( 'prepended', $namespaces );
		$this->assertContains( 'project', $namespaces );
		$this->assertContains( '__main__', $namespaces );
	}
}
