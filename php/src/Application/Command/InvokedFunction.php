<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpIntegrator\IndexDataAdapter;

use PhpIntegrator\Application\Command as BaseCommand;

use PhpParser\Lexer;
use PhpParser\NodeTraverser;

/**
 * Command that shows information about a method or function being invoked.
 */
class InvokedFunction extends BaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected function attachOptions(OptionCollection $optionCollection)
    {
        $optionCollection->add('source?', 'The file or directory to index.')->isa('string');
        $optionCollection->add('stdin?', 'If set, file contents will not be read from disk but the contents from STDIN will be used instead.');
        $optionCollection->add('offset:', 'The character byte offset into the code to use for the determination.')->isa('number');
    }

    /**
     * {@inheritDoc}
     */
    protected function process(ArrayAccess $arguments)
    {
        if (!isset($arguments['source']) && (!isset($arguments['stdin']) || !$arguments['stdin']->value)) {
            throw new UnexpectedValueException('Either a --source file must be supplied or --stdin must be passed!');
        }

        if (!isset($arguments['offset'])) {
            throw new UnexpectedValueException('An --offset must be supplied into the source code!');
        }

        $lexer = new Lexer([
            'usedAttributes' => ['startFilePos', 'endFilePos']
        ]);

        $code = null;

        if (isset($arguments['stdin']) && $arguments['stdin']->value) {
            // NOTE: This call is blocking if there is no input!
            $code = file_get_contents('php://stdin');
        } else {
            $code = file_get_contents($arguments['source']->value);
        }

        $parser = (new \PhpParser\ParserFactory())->create(\PhpParser\ParserFactory::PREFER_PHP7, $lexer/*, [
            'throwOnError' => false
        ]*/);

        // TODO: Deal with errors if at all possible.
        // die(var_dump(substr($code, 0, $arguments['offset']->value)));

        $nodes = $parser->parse($code);

        $position = $arguments['offset']->value;

        $visitor = new InvokedFunction\QueryingVisitor($code, $position);

        $traverser = new NodeTraverser(false);
        $traverser->addVisitor($visitor);
        $traverser->traverse($nodes);

        if ($visitor->isValid()) {
            return $this->outputJson(true, [
                'name'                  => $visitor->getName(),
                'type'                  => $visitor->getType(),
                'callStack'             => $visitor->getCallStack(),
                'argumentIndex'         => $visitor->getArgumentIndex(),
                'parameterListStartPos' => $visitor->getParameterListStartPos()
            ]);
        }

        return $this->outputJson(false, 'No invocation found!');
    }
}
