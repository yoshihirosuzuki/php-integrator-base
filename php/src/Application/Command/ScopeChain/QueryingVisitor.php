<?php

namespace PhpIntegrator\Application\Command\ScopeChain;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Visitor that queries the nodes for scope information.
 */
class QueryingVisitor extends NodeVisitorAbstract
{
    /**
     * @var int
     */
    protected $position;

    /**
     * @var array[]
     */
    protected $chain = [];

    /**
     * @var array[]
     */
    protected $chainItemsInScope = [];

    /**
     * Constructor.
     *
     * @param int          $position
     */
    public function __construct($position)
    {
        $this->position = $position;
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        $startFilePos = $node->getAttribute('startFilePos');
        $endFilePos = $node->getAttribute('endFilePos');

        if ($startFilePos >= $this->position) {
            // We've gone beyond the requested position, there is nothing here that can still be relevant anymore.
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        if (!$node instanceof Node\Expr && !$node instanceof Node\Stmt) {
            return;
        }

        $chainItem = $this->generateChainItemForNode($node);

        if ($node instanceof Node\Stmt\ClassLike ||
            $node instanceof Node\FunctionLike ||
            $node instanceof Node\Stmt\If_ ||
            $node instanceof Node\Stmt\TryCatch ||
            $node instanceof Node\Stmt\While_ ||
            $node instanceof Node\Stmt\For_ ||
            $node instanceof Node\Stmt\Foreach_ ||
            $node instanceof Node\Stmt\Do_ ||
            $node instanceof Node\Stmt\Switch_ ||
            $node instanceof Node\Expr
        ) {
            $this->chainItemsInScope = [];

            if ($startFilePos <= $this->position && $endFilePos >= $this->position) {
                $this->chain[] = $chainItem;
            }
        } else {
            $this->chainItemsInScope[] = $chainItem;
        }
    }

    /**
     * @param Node $node
     *
     * @return array
     */
    protected function generateChainItemForNode(Node $node)
    {
        $typeName = mb_substr(get_class($node), mb_strlen('PhpParser\\Node\\'));

        if (mb_substr($typeName, -1) === '_') {
            $typeName = mb_substr($typeName, 0, -1);
        }

        return [
            'type' => $typeName
        ];
    }

    /**
     * @return array[]
     */
    public function getChain()
    {
        return array_merge($this->chain, $this->chainItemsInScope);
    }
}
