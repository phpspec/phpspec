<?php

namespace PhpSpec\Runner\Maintainer;

use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\SpecificationInterface;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\Runner\CollaboratorManager;

use PhpSpec\Exception\Example as ExampleException;

class ErrorMaintainer implements MaintainerInterface
{
    private $errorLevel;
    private $errorHandler;

    public function __construct($errorLevel)
    {
        $this->errorLevel = $errorLevel;
    }

    public function supports(ExampleNode $example)
    {
        return true;
    }

    public function prepare(ExampleNode $example, SpecificationInterface $context,
                            MatcherManager $matchers, CollaboratorManager $collaborators)
    {
        $this->errorHandler = set_error_handler(array($this, 'errorHandler'), $this->errorLevel);
    }

    public function teardown(ExampleNode $example, SpecificationInterface $context,
                             MatcherManager $matchers, CollaboratorManager $collaborators)
    {
        if (null !== $this->errorHandler) {
            set_error_handler($this->errorHandler);
        }
    }

    public function getPriority()
    {
        return 999;
    }

    /**
     * Custom error handler.
     *
     * This method used as custom error handler when step is running.
     *
     * @see set_error_handler()
     *
     * @param integer $level
     * @param string  $message
     * @param string  $file
     * @param integer $line
     *
     * @return Boolean
     *
     * @throws ErrorException
     */
    final public function errorHandler($level, $message, $file, $line)
    {
        $regex = '/^Argument (\d)+ passed to (?:([\w\\\]+)::)?(\w+)\(\) must (?:be an instance of|implement interface) ([\w\\\]+),(?: instance of)? ([\w\\\]+) given/';

        if (E_RECOVERABLE_ERROR === $level && preg_match($regex, $message, $matches)) {
            list($_, $_, $class, $method, $hint, $type) = $matches;

            if (in_array('PhpSpec\SpecificationInterface', class_implements($class))) {
                return true;
            }
        }

        if (0 !== error_reporting()) {
            throw new ExampleException\ErrorException($level, $message, $file, $line);
        }

        // error reporting turned off or more likely suppressed with @
        return false;
    }
}
