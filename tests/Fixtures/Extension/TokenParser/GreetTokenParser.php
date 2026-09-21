<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\TokenParser
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\TokenParser;

use OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension\Node\GreetNode;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * A tag is one class, and this is it.
 *
 * It extends Twig's abstract token parser, which implements the interface
 * autoconfiguration looks for, so nothing has to be registered anywhere.
 */
final class GreetTokenParser extends AbstractTokenParser {
	/**
	 * Parse `{% greet <expression> %}`.
	 *
	 * @param Token $token The tag token.
	 * @return Node
	 */
	public function parse( Token $token ): Node {
		$name = $this->parser->parseExpression();

		$this->parser->getStream()->expect( Token::BLOCK_END_TYPE );

		return new GreetNode( $name, $token->getLine() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function getTag(): string {
		return 'greet';
	}
}
