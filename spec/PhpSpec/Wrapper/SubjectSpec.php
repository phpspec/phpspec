<?php

namespace spec\PhpSpec\Wrapper;

use PhpSpec\Wrapper\Wrapper;
use PhpSpec\Wrapper\Subject\WrappedObject;
use PhpSpec\Wrapper\Subject\Caller;
use PhpSpec\Wrapper\Subject\SubjectWithArrayAccess;
use PhpSpec\Wrapper\Subject\Expectation;
use PhpSpec\Wrapper\Subject\ExpectationFactory;

use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Event\ExpectationEvent;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Matcher\MatcherInterface;
use PhpSpec\ObjectBehavior;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\Wrapper\Unwrapper;
Use Prophecy\Argument;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SubjectSpec extends ObjectBehavior
{
    function let(Wrapper $wrapper, WrappedObject $wrappedObject, Caller $caller,
                 SubjectWithArrayAccess $arrayAccess, ExpectationFactory $expectationFactory)
    {
        $this->beConstructedWith(new \Exception(), $wrapper, $wrappedObject, $caller, $arrayAccess, $expectationFactory);
    }
}
