<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Unit
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * A promise asserted rather than made.
 *
 * Every twig/*-extra package works with this bundle because none of them is known to
 * it: no list, no class_exists() probe, no recipe in the source. A package published
 * tomorrow works the same way, and the only way to keep that true is to assert it.
 */
#[CoversNothing]
final class NoPackageKnowledgeTest extends TestCase {

	/**
	 * The package names, and the namespace every one of their classes lives under.
	 *
	 * @var array<int, string>
	 */
	private const FORBIDDEN = array(
		'twig/intl-extra',
		'twig/string-extra',
		'twig/html-extra',
		'twig/cssinliner-extra',
		'twig/inky-extra',
		'twig/markdown-extra',
		'twig/cache-extra',
		'Twig\\Extra',
	);

	/**
	 * Every PHP file this package ships.
	 *
	 * @return array<int, string>
	 */
	private function sourceFiles(): array {
		$files = array();

		$walker = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( dirname( __DIR__, 2 ) . '/src', \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $walker as $file ) {
			if ( $file instanceof \SplFileInfo && 'php' === $file->getExtension() ) {
				$files[] = $file->getPathname();
			}
		}

		sort( $files );

		return $files;
	}

	/**
	 * Nothing this package ships names one of those packages, one of their namespaces,
	 * or one of their classes.
	 *
	 * @return void
	 */
	public function testTheSourceMentionsNoExtraPackage(): void {
		$files      = $this->sourceFiles();
		$filesystem = new Filesystem();
		$offenders  = array();

		$this->assertNotSame( array(), $files, 'The check has to have something to check.' );

		foreach ( $files as $file ) {
			$contents = $filesystem->readFile( $file );

			foreach ( self::FORBIDDEN as $needle ) {
				if ( str_contains( $contents, $needle ) ) {
					$offenders[] = sprintf( '%s mentions "%s"', basename( $file ), $needle );
				}
			}
		}

		$this->assertSame( array(), $offenders );
	}
}
