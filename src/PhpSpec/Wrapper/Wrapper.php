<?php

namespace PhpSpec\Wrapper;

use PhpSpec\Runner\MatcherManager;
use PhpSpec\Formatter\Presenter\PresenterInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use PhpSpec\Loader\Node\ExampleNode;

use PhpSpec\Wrapper\Subject\WrappedObject;
use PhpSpec\Wrapper\Subject\Caller;
use PhpSpec\Wrapper\Subject\SubjectWithArrayAccess;
use PhpSpec\Wrapper\Subject\ExpectationFactory;

class Wrapper
{
    private $matchers;
    private $presenter;
    private $dispatcher;
    private $example;

    public function __construct(MatcherManager $matchers, PresenterInterface $presenter,
        EventDispatcherInterface $dispatcher, ExampleNode $example)
    {
        $this->matchers = $matchers;
        $this->presenter = $presenter;
        $this->dispatcher = $dispatcher;
        $this->example = $example;
    }

    public function wrap($value = null)
    {
        $wrappedObject      = new WrappedObject($value, $this->presenter);
        $caller             = new Caller($wrappedObject, $this->example, $this->dispatcher, $this->presenter, $this->matchers, $this);
        $arrayAccess        = new SubjectWithArrayAccess($caller, $this->presenter, $this->dispatcher);
        $expectationFactory = new ExpectationFactory($this->example, $this->dispatcher, $this->matchers);

        return new Subject(
            $value, $this, $wrappedObject, $caller, $arrayAccess, $expectationFactory
        );
    }
}
