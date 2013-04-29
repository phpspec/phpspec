<?php

namespace PhpSpec\Runner;

use PhpSpec\Matcher\MatcherInterface;

use PhpSpec\Exception\Wrapper\MatcherNotFoundException;
use PhpSpec\Formatter\Presenter\PresenterInterface;

class MatcherManager
{
    private $presenter;
    private $matchers = array();

    public function __construct(PresenterInterface $presenter)
    {
        $this->presenter = $presenter;
    }

    public function add(MatcherInterface $matcher)
    {
        $this->matchers[] = $matcher;
        @usort($this->matchers, function($matcher1, $matcher2) {
            return $matcher2->getPriority() - $matcher1->getPriority();
        });
    }

    public function find($keyword, $subject, array $arguments)
    {
        foreach ($this->matchers as $matcher) {
            if (true === $matcher->supports($keyword, $subject, $arguments)) {
                return $matcher;
            }
        }

        throw new MatcherNotFoundException(
            sprintf('No %s(%s) matcher found for %s.',
                $this->presenter->presentString($keyword),
                $this->presenter->presentValue($arguments),
                $this->presenter->presentValue($subject)
            ),
            $keyword, $subject, $arguments
        );
    }
}
