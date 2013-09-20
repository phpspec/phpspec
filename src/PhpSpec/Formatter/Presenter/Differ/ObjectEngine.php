<?php

namespace PhpSpec\Formatter\Presenter\Differ;

use PhpSpec\Formatter\Presenter\Differ\EngineInterface;

class ObjectEngine implements EngineInterface
{
    private $engine;
    private $diffSource;

    public function __construct(EngineInterface $engine, $diffSource = null)
    {
        $this->engine = $engine;
        $this->diffSource = $diffSource;

        if (null === $diffSource) {
            $this->diffSource = function ($value) {
                return print_r($value, true);
            };
        }

        if (!is_callable($this->diffSource)) {
            throw new \InvalidArgumentException(sprintf(
                'Argument 2 passed to ObjectEngine::__construct must be a callable, %s given.',
                gettype($diffSource)
            ));
        }
    }

    public function supports($expected, $actual)
    {
        return is_object($expected) && is_object($actual);
    }

    public function compare($expected, $actual)
    {
        $expectedString = call_user_func($this->diffSource, $expected);
        $actualString   = call_user_func($this->diffSource, $actual);

        return $this->engine->compare($expectedString, $actualString);
    }
}
