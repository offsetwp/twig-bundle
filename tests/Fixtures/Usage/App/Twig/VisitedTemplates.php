<?php
/**
 * A fixture project of the OffsetWP Twig Bundle test suite.
 *
 * @author Jérôme Wohlschlegel
 * @package App\Twig
 */

declare( strict_types=1 );

namespace App\Twig;

use Twig\Environment;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * A node visitor that changes nothing and writes down which templates it compiled.
 *
 * It is a service like any other, so a test reads what it recorded by asking the
 * container for it — which also says that the instance Twig received is the instance
 * the container holds.
 */
final class VisitedTemplates implements NodeVisitorInterface {

	/**
	 * The name of every template this visitor saw compiled, in order.
	 *
	 * @var array<int, string>
	 */
	private array $names = array();

	/**
	 * {@inheritDoc}
	 *
	 * @param Node        $node        The node being entered.
	 * @param Environment $environment The environment compiling it.
	 * @return Node
	 */
	public function enterNode( Node $node, Environment $environment ): Node {
		if ( $node instanceof ModuleNode ) {
			$this->names[] = (string) $node->getSourceContext()?->getName();
		}

		return $node;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Node        $node        The node being left.
	 * @param Environment $environment The environment compiling it.
	 * @return Node
	 */
	public function leaveNode( Node $node, Environment $environment ): Node {
		return $node;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return int
	 */
	public function getPriority(): int {
		return 0;
	}

	/**
	 * What this visitor saw.
	 *
	 * @return array<int, string>
	 */
	public function names(): array {
		return $this->names;
	}
}
