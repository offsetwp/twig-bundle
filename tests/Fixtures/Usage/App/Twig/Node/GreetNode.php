<?php
/**
 * A fixture project of the OffsetWP Twig Bundle test suite.
 *
 * @author Jérôme Wohlschlegel
 * @package App\Twig\Node
 */

declare( strict_types=1 );

namespace App\Twig\Node;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;

/**
 * What the greet tag compiles to.
 *
 * This is the one class of the project that must never become a service: it is built
 * by the parser with the parsed expression, and a container asked to autowire it would
 * have nothing to hand it. The services file excludes this directory, which is what a
 * project does with its node classes.
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
			->write( 'yield "Bonjour, " . ' )
			->subcompile( $this->getNode( 'name' ) )
			->raw( ";\n" );
	}
}
