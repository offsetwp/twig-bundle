<?php
/**
 * OffsetWP Twig Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\TwigBundle\Tests\Fixtures\Extension;

use Twig\Environment;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * A node visitor that changes nothing and writes down that it ran.
 *
 * It records once per compiled template, on the root node, so that a test can read
 * both that it was invoked at all and in what order it was invoked against others.
 *
 * Twig sorts visitors by the priority they report themselves; this one reports zero,
 * so what decides the order between two of them is the order they were added in —
 * which is the tag priority this bundle sorts on.
 */
final class RecordingNodeVisitor implements NodeVisitorInterface {

	/**
	 * The labels of every visitor that ran, in order.
	 *
	 * @var array<int, string>
	 */
	public static array $visited = array();

	/**
	 * Constructor.
	 *
	 * @param string $label How this visitor signs the record.
	 * @return void
	 */
	public function __construct( private string $label = 'default' ) {
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Node        $node        The node being entered.
	 * @param Environment $environment The environment compiling it.
	 * @return Node
	 */
	public function enterNode( Node $node, Environment $environment ): Node {
		if ( $node instanceof ModuleNode ) {
			self::$visited[] = $this->label;
		}

		return $node;
	}

	/**
	 * {@inheritDoc}
	 *
	 * This visitor changes nothing, so it always hands the node back. The interface
	 * allows null, meaning "remove this node"; narrowing the return type here says
	 * plainly that it never does.
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
}
