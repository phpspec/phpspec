<?php

namespace PhpSpec\Wrapper;

class SubjectFactory
{
    private $matchers;
    private $presenter;
    private $dispatcher;

    public function __construct($matchers, $presenter, $dispatcher)
    {
        $this->matchers = $matchers;
        $this->presenter = $presenter;
        $this->dispatcher = $dispatcher;
    }

    public function create($value = null, $example)
    {
        return new Subject(
            $value, $this->matchers, $this->presenter, $this->dispatcher, $example
        );
    }
}
