<?php

namespace spec\PhpSpec\Matcher\Iterate;

use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Matcher\Iterate\SubjectElementDoesNotMatchException;
use PhpSpec\Matcher\Iterate\SubjectHasFewerElementsException;
use PhpSpec\Matcher\Iterate\SubjectHasMoreElementsException;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

/**
 * @author Kamil Kokot <kamil.kokot@lakion.com>
 */
final class IterablesMatcherSpec extends ObjectBehavior
{
    function let(Presenter $presenter)
    {
        $presenter->presentValue(Argument::any())->will(function ($subject) {
            return '"' . $subject[0] . '"';
        });

        $this->beConstructedWith($presenter);
    }

    function it_should_throw_an_invalid_argument_exception_if_subject_is_not_iterable()
    {
        $this
            ->shouldThrow(new \InvalidArgumentException('Subject value should be an array or implement \Traversable.'))
            ->during('match', ['not iterable', []])
        ;

        $this
            ->shouldThrow(new \InvalidArgumentException('Subject value should be an array or implement \Traversable.'))
            ->during('match', [9, []])
        ;

        $this
            ->shouldThrow(new \InvalidArgumentException('Subject value should be an array or implement \Traversable.'))
            ->during('match', [new \stdClass(), []])
        ;
    }

    function it_should_throw_an_invalid_argument_exception_if_expected_value_is_not_iterable()
    {
        $this
            ->shouldThrow(new \InvalidArgumentException('Expected value should be an array or implement \Traversable.'))
            ->during('match', [[], 'not iterable'])
        ;

        $this
            ->shouldThrow(new \InvalidArgumentException('Expected value should be an array or implement \Traversable.'))
            ->during('match', [[], 9])
        ;

        $this
            ->shouldThrow(new \InvalidArgumentException('Expected value should be an array or implement \Traversable.'))
            ->during('match', [[], new \stdClass()])
        ;
    }

    function it_should_throw_an_exception_if_subject_has_less_elements_than_expected()
    {
        $this
            ->shouldThrow(new SubjectHasFewerElementsException())
            ->during('match', [['a' => 'b'], ['a' => 'b', 'c' => 'd']])
        ;
    }

    function it_should_throw_an_exception_if_subject_has_more_elements_than_expected()
    {
        $this
            ->shouldThrow(new SubjectHasMoreElementsException())
            ->during('match', [['a' => 'b', 'c' => 'd'], ['a' => 'b']])
        ;
    }

    function it_should_throw_an_exception_if_subject_element_does_not_match_the_expected_one()
    {
        $this
            ->shouldThrow(new SubjectElementDoesNotMatchException(0, '"a"', '"b"', '"a"', '"c"'))
            ->during('match', [['a' => 'b'], ['a' => 'c']])
        ;

        $this
            ->shouldThrow(new SubjectElementDoesNotMatchException(0, '"a"', '"b"', '"c"', '"b"'))
            ->during('match', [['a' => 'b'], ['c' => 'b']])
        ;

        $this
            ->shouldThrow(new SubjectElementDoesNotMatchException(1, '"c"', '"d"', '"c"', '"e"'))
            ->during('match', [['a' => 'b', 'c' => 'd'], ['a' => 'b', 'c' => 'e']])
        ;
    }

    function it_should_not_throw_any_exception_if_subject_iterates_as_expected()
    {
        $this
            ->shouldNotThrow()
            ->during('match', [['a' => 'b', 'c' => 'd'], ['a' => 'b', 'c' => 'd']])
        ;

        $this
            ->shouldNotThrow()
            ->during('match', [['a' => 'b', 'c' => 'd'], new \ArrayIterator(['a' => 'b', 'c' => 'd'])])
        ;

        $this
            ->shouldNotThrow()
            ->during('match', [new \ArrayIterator(['a' => 'b', 'c' => 'd']), ['a' => 'b', 'c' => 'd']])
        ;

        $this
            ->shouldNotThrow()
            ->during('match', [['a' => 'b', 'c' => 'd'], new \ArrayObject(['a' => 'b', 'c' => 'd'])])
        ;

        $this
            ->shouldNotThrow()
            ->during('match', [new \ArrayObject(['a' => 'b', 'c' => 'd']), ['a' => 'b', 'c' => 'd']])
        ;

        $this
            ->shouldNotThrow()
            ->during('match', [$this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']), ['a' => 'b', 'c' => 'd']])
        ;

        $this
            ->shouldNotThrow()
            ->during('match', [['a' => 'b', 'c' => 'd'], $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd'])])
        ;
    }

    /**
     * @param array $array
     *
     * @return \Generator
     */
    private function createGeneratorReturningArray(array $array)
    {
        foreach ($array as $key => $value) {
            yield $key => $value;
        }
    }
}
