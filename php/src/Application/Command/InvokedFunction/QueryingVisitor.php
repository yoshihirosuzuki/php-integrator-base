<?php

namespace PhpIntegrator\Application\Command\InvokedFunction;

use UnexpectedValueException;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Visitor that queries the nodes for information about an invoked function or method.
 */
class QueryingVisitor extends NodeVisitorAbstract
{
    /**
     * The node of the function, method, or constructor call.
     *
     * @param Node\Expr|null
     */
    protected $callNode = null;

    /**
     * The position into the file to examine.
     *
     * @var int
     */
    protected $position;

    /**
     * The code being examined.
     *
     * @var string
     */
    protected $code;

    /**
     * The call stack.
     *
     * @var array
     */
    protected $callStack = [];

    /**
     * Constructor.
     *
     * @param string $code
     * @param int    $position
     */
    public function __construct($code, $position)
    {
        $this->code = $code;
        $this->position = $position;
    }

    /**
     * {@inheritDoc}
     */
    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        if ($node instanceof Node\Expr\FuncCall) {
            if ($this->position < $node->getAttribute('startFilePos') ||
                $this->position > $node->getAttribute('endFilePos')
            ) {
                // This call doesn't interest us, no use in parsing its children.
                return NodeTraverser::DONT_TRAVERSE_CHILDREN;
            }

            $this->parseFuncCall($node);
        } elseif ($node instanceof Node\Expr\MethodCall) {
            if ($this->position < $node->getAttribute('startFilePos') ||
                $this->position > $node->getAttribute('endFilePos')
            ) {
                // This call doesn't interest us, no use in parsing its children.
                return NodeTraverser::DONT_TRAVERSE_CHILDREN;
            }

            $this->parseMethodCall($node);
       }

        if ($node->getAttribute('startFilePos') > $this->position) {
            // We've already passed the position, other nodes are no longer relevant. (Too bad we can't halt the
            // entire traversal.)
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        // TODO: isValid is still returning true of the buffer position is on a function call, but not inside one of
        // its parameters. getArgumentIndex must find at least the first argument and isValid must fetch and validate
        // it.

        // TODO: argumentIndex is not entirely correct.

        // TODO: Also deal with StaticCall, should be treated the same way as MethodCall.
        // TODO: Add support for constructor calls, type: if isClassName then 'instantiation' else 'function'.

        // TODO: Also deal with the case where 'name' is an Expr, can probably happen in cases like $this->{method}.
        // Simply don't return any invocation info here or return the expression as string.

        // TODO: Rewrite CoffeeScript side to allow calling this method asynchronously using promises. Remove
        // getInvocationInfoAt entirely.
    }

    /**
     * Parses a function call.
     *
     * @param Node\Expr\FuncCall $node
     */
    protected function parseFuncCall(Node\Expr\FuncCall $node)
    {
        $this->callStack = [$node->name];
        $this->callNode = $node;
    }

    /**
     * Parses a method call.
     *
     * @param Node\Expr\MethodCall $node
     */
    protected function parseMethodCall(Node\Expr\MethodCall $node)
    {
        $this->callNode = $node;

        $this->callStack = [];

        while ($node) {
            if ($node instanceof Node\Expr\MethodCall) {
                $this->callStack[] = $node->name . '()';
                $node = $node->var;
            } elseif ($node instanceof Node\Expr\PropertyFetch) {
                $this->callStack[] = $node->name;
                $node = $node->var;
            } elseif ($node instanceof Node\Expr\StaticPropertyFetch) {
                $this->callStack[] = $node->name;
                $node = null;
            } else {
                $name = $node->name;

                if ($node instanceof Node\Expr\Variable) {
                    $name = '$' . $node->name;
                }

                $this->callStack[] = $name;
                $node = null;
            }
        }

        $this->callStack = array_reverse($this->callStack);
    }

    /**
     * Returns whether a valid invocation was found.
     *
     * @return bool
     */
    public function isValid()
    {
        return !!$this->callNode;
    }

    /**
     * Returns the name of the function or method being invoked.
     *
     * @return string
     */
    public function getName()
    {
        return $this->callNode->name;
    }

    /**
     * Returns the type of invocation: 'function' for functions and methods and 'instantiation' for constructors.
     *
     * @return string
     */
    public function getType()
    {
        return 'function'; // TODO: 'instantiation' for constructors.
    }

    /**
     * Returns the call stack.
     *
     * @return array
     */
    public function getCallStack()
    {
        return $this->callStack;
    }

    /**
     * Returns the position the parameter list for the invocation starts at.
     *
     * @return int
     */
    public function getParameterListStartPos()
    {
        if ($this->callNode->name instanceof Node\Name ||
            $this->callNode->name instanceof Node\Expr\Variable) {
            return $this->callNode->name->getAttribute('endFilePos');
        }

        // The name is not a node, meaning there is no direct way to fetch the start of the parameter list (or the end
        // of the name of the method in the buffer, just before the opening parenthesis). We must manually scan the
        // file. The endFilePos also returns the position just before the closing parenthesis, hence the increment.
        $i = $this->callNode->var->getAttribute('endFilePos') + 1;

        $nameLength = mb_strlen($this->callNode->name);

        $foundDash = false;
        $foundArrow = false;

        while (true) {
            if ($this->code[$i] === ' ' || $this->code[$i] === '\t' || $this->code[$i] === '\n') {
                // Do nothing.
            } elseif ($this->code[$i] === '-') {
                if ($foundDash) {
                    throw new UnexpectedValueException('Found another dash, this should never happen!');
                }

                $foundDash = true;
            } elseif ($this->code[$i] === '>') {
                if (!$foundDash) {
                    throw new UnexpectedValueException('Found an arrow, but no dash! Tis should never happen!');
                } elseif ($foundArrow) {
                    throw new UnexpectedValueException('Found another arrow, this should never happen!');
                }

                $foundArrow = true;
            } elseif ($foundDash && $foundArrow) {
                if (mb_substr($this->code, $i, $nameLength) !== $this->callNode->name) {
                    throw new UnexpectedValueException('Found an unexpected name, this should never happen!');
                }

                $i += $nameLength;
                break;
            } else {
                throw new UnexpectedValueException('Found unexpected token, this should never happen!');
            }

            ++$i;
        }

        return $i;
    }

    /**
     * Retrieves the index of the argument that is being specified at the passed position. The index starts from 0.
     *
     * @return int
     */
    public function getArgumentIndex()
    {
        $argumentIndex = 0;

        if ($this->callNode instanceof Node\Expr\FuncCall ||
            $this->callNode instanceof Node\Expr\MethodCall
        ) {
            /** @var Node\Arg $arg */
            foreach ($this->callNode->args as $arg) {
                // NOTE: endFilePos === startFilePos for one-character arguments. Quirk?
                if ($this->position > ($arg->getAttribute('endFilePos') + 1)) {
                    ++$argumentIndex;
                }
            }
        }

        return $argumentIndex;;
    }
}
