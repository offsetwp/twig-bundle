<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\Node
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\Node;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;

/**
 * What the greet tag compiles to.
 *
 * The yield-ready attribute is what tells Twig this node hands its output back
 * rather than printing it, which is what the use_yield option needs of a custom
 * node. A node written today should carry it.
 */
#[YieldReady]
final class GreetNode extends Node {
	/**
	 * Constructor.
	 *
	 * @param AbstractExpression $name The greeted name expression.
	 * @param int                $line The template line.
	 * @return void
	 */
	public function __construct( AbstractExpression $name, int $line ) {
		parent::__construct( array( 'name' => $name ), array(), $line );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Compiler $compiler The compiler.
	 * @return void
	 */
	public function compile( Compiler $compiler ): void {
		$compiler
			->addDebugInfo( $this )
			->write( 'yield "Hello, " . ' )
			->subcompile( $this->getNode( 'name' ) )
			->raw( ";\n" );
	}
}
