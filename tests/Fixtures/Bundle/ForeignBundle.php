<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Bundle
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Bundle;

use OffsetWP\Framework\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * A bundle written for another Twig integration, used here as it is.
 *
 * Everything it does to Twig goes through the ids and the tags that integration
 * defines, written as literal strings, the way a package that has never heard of this
 * bundle writes them: a compiler pass editing the "twig" definition, a service injected
 * with "twig.loader", and a class of its own marked "twig.safe_class". Each of the three
 * used to fail here without a word.
 */
final class ForeignBundle extends Bundle {

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerBuilder $container The service container.
	 * @return void
	 */
	public function build( ContainerBuilder $container ): void {
		parent::build( $container );

		$container->addCompilerPass( new ForeignPass() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed>  $config    The processed configuration, empty here.
	 * @param ContainerConfigurator $container The container configurator.
	 * @param ContainerBuilder      $builder   The service container.
	 * @return void
	 */
	public function loadExtension( array $config, ContainerConfigurator $container, ContainerBuilder $builder ): void {
		$builder->register( 'foreign.template_finder', TemplateFinder::class )
			->setArguments( array( new Reference( 'twig.loader' ) ) )
			->setPublic( true );

		// Registered under its class name with no class of its own, as such bundles do.
		$builder->register( Attributes::class )
			->addResourceTag( 'twig.safe_class', array( 'strategy' => 'html' ) );
	}
}
