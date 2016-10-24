<?php

namespace PhpSpec\Matcher\Iterate;

final class IterablesMatcher
{
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
                    $subjectKey,
                    $subjectValue,
                    $expectedIterator->key(),
                    $expectedIterator->current()
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
