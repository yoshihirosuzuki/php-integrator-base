<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpParser\Lexer;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;

/**
 * Retrieves a list (chain) of node names that are active at a specific location in a file.
 *
 * This information can be useful to determine what kind of code is at the specified location and where it is nested in
 * (for example, a method call that is inside a method that is in turn inside a class).
 */
class ScopeChain extends AbstractCommand
{
    /**
     * @var Parser
     */
    protected $parser;

    /**
     * @inheritDoc
     */
    protected function attachOptions(OptionCollection $optionCollection)
    {
        $optionCollection->add('file:', 'The file to examine.')->isa('string');
        $optionCollection->add('stdin?', 'If set, file contents will not be read from disk but the contents from STDIN will be used instead.');
        $optionCollection->add('as-selector?', 'If set, a single CSS selector-like string will be returned instead of a list of objects.');
        $optionCollection->add('charoffset?', 'If set, the input offset will be treated as a character offset instead of a byte offset.');
        $optionCollection->add('offset:', 'The character byte offset into the code to use for the determination.')->isa('number');
    }

    /**
     * @inheritDoc
     */
    protected function process(ArrayAccess $arguments)
    {
        if (!isset($arguments['file'])) {
            throw new UnexpectedValueException('Either a --file file must be supplied or --stdin must be passed!');
        } elseif (!isset($arguments['offset'])) {
            throw new UnexpectedValueException('An --offset must be supplied into the source code!');
        }

        $code = $this->getSourceCode(
            isset($arguments['file']) ? $arguments['file']->value : null,
            isset($arguments['stdin']) && $arguments['stdin']->value
        );

        $offset = $arguments['offset']->value;

        if (isset($arguments['charoffset']) && $arguments['charoffset']->value == true) {
            $offset = $this->getCharacterOffsetFromByteOffset($offset, $code);
        }

        $result = $this->getScopeChain(
           $code,
           $offset,
           isset($arguments['as-selector']) ? !!$arguments['as-selector']->value : false
        );

        return $this->outputJson(true, $result);
    }

    /**
     * @param string $code
     * @param int    $offset
     * @param bool   $asSelector
     *
     * @return array[]
     */
    public function getScopeChain($code, $offset, $asSelector)
    {
        $parser = $this->getParser();

        $nodes = $parser->parse($code);

        $visitor = new ScopeChain\QueryingVisitor($offset);

        $traverser = new NodeTraverser(false);
        $traverser->addVisitor(new Visitor\ScopeLimitingVisitor($offset));
        $traverser->addVisitor($visitor);

        if ($nodes !== null) {
            $traverser->traverse($nodes);
        }

        $chain = $visitor->getChain();

        if ($asSelector) {
            $types = array_map(function (array $item) {
                return $item['type'];
            }, $chain);

            return implode('.', $types);
        }

        return $chain;
    }

    /**
     * @return Parser
     */
    protected function getParser()
    {
        if (!$this->parser) {
            $lexer = new Lexer([
                'usedAttributes' => [
                    'startFilePos', 'endFilePos'
                ]
            ]);

            $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7, $lexer, [
                'throwOnError' => false
            ]);
        }

        return $this->parser;
    }
}
