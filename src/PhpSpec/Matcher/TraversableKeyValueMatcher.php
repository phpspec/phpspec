<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Matcher;

use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Exception\Example\FailureException;
use ArrayAccess;

final class TraversableKeyValueMatcher extends BasicMatcher
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
     * {@inheritdoc}
     */
    public function supports($name, $subject, array $arguments)
    {
        return 'haveKeyWithValue' === $name
            && 2 === count($arguments)
            && $subject instanceof \Traversable
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function matches($subject, array $arguments)
    {
        foreach ($subject as $key => $value) {
            if ($key === $arguments[0] && $value === $arguments[1]) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFailureException($name, $subject, array $arguments)
    {
        return new FailureException(sprintf(
            'Expected %s to have an element with %s key and %s value, but it does not.',
            $this->presenter->presentValue($subject),
            $this->presenter->presentValue($arguments[0]),
            $this->presenter->presentValue($arguments[1])
        ));
    }

    /**
     * {@inheritdoc}
     */
    protected function getNegativeFailureException($name, $subject, array $arguments)
    {
        return new FailureException(sprintf(
            'Expected %s not to have an element with %s key and %s value, but it does.',
            $this->presenter->presentValue($subject),
            $this->presenter->presentValue($arguments[0]),
            $this->presenter->presentValue($arguments[1])
        ));
    }
}
