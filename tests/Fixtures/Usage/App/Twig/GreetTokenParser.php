<?php
/**
 * A fixture project of the OffsetWP Twig Bundle test suite.
 *
 * @author Jérôme Wohlschlegel
 * @package App\Twig
 */

declare( strict_types=1 );

namespace App\Twig;

use App\Twig\Node\GreetNode;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * A tag of the project's own: {% greet <expression> %}.
 */
final class GreetTokenParser extends AbstractTokenParser {
	/**
	 * Parse the tag.
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
