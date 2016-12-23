<?php

namespace PhpSpec\Matcher\Iterate;

use PhpSpec\Formatter\Presenter\Presenter;

final class IterablesMatcher
{
    /**
     * @var Presenter
     */
    private $presenter;

    /**
     * @param Presenter $presenter
     */
    public function __construct(Presenter $presenter)
    {
        $this->presenter = $presenter;
    }

    /**
     * @param array|\Traversable $subject
     * @param array|\Traversable $expected
     *
     * @throws \InvalidArgumentException
     * @throws SubjectElementDoesNotMatchException
     * @throws SubjectHasFewerElementsException
     * @throws SubjectHasMoreElementsException
     */
    public function match($subject, $expected)
    {
        if (!$this->isIterable($subject)) {
            throw new \InvalidArgumentException('Subject value should be an array or implement \Traversable.');
        }

        if (!$this->isIterable($expected)) {
            throw new \InvalidArgumentException('Expected value should be an array or implement \Traversable.');
        }

        $expectedIterator = $this->createIteratorFromIterable($expected);

        $count = 0;
        foreach ($subject as $subjectKey => $subjectValue) {
            if (!$expectedIterator->valid()) {
                throw new SubjectHasMoreElementsException();
            }

            if ($subjectKey !== $expectedIterator->key() || $subjectValue !== $expectedIterator->current()) {
                throw new SubjectElementDoesNotMatchException(
                    $count,
                    $this->presenter->presentValue($subjectKey),
                    $this->presenter->presentValue($subjectValue),
                    $this->presenter->presentValue($expectedIterator->key()),
                    $this->presenter->presentValue($expectedIterator->current())
                );
            }

            $expectedIterator->next();
            ++$count;
        }

        if ($expectedIterator->valid()) {
            throw new SubjectHasFewerElementsException();
        }
    }

    /**
     * @param mixed $variable
     *
     * @return bool
     */
    private function isIterable($variable)
    {
        return is_array($variable) || $variable instanceof \Traversable;
    }

    /**
     * @param array|\Traversable $iterable
     *
     * @return \Iterator
     */
    private function createIteratorFromIterable($iterable)
    {
        if (is_array($iterable)) {
            return new \ArrayIterator($iterable);
        }

        $iterator = new \IteratorIterator($iterable);
        $iterator->rewind();

        return $iterator;
    }
}
