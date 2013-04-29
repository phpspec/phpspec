<?php

namespace PhpSpec\Formatter\Presenter\Differ;

class Differ
{
    private $engines = array();

    public function addEngine(EngineInterface $engine)
    {
        $this->engines[] = $engine;
    }

    public function compare($expected, $actual)
    {
        foreach ($this->engines as $engine) {
            if ($engine->supports($expected, $actual)) {
                return rtrim($engine->compare($expected, $actual));
            }
        }
    }
}
