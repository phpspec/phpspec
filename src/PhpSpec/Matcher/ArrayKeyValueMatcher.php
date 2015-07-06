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

use ArrayAccess;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Formatter\Presenter\PresenterInterface;

class ArrayKeyValueMatcher extends BasicMatcher
{
    /**
     * @var PresenterInterface
     */
    private $presenter;

    /**
     * @param PresenterInterface $presenter
     */
    public function __construct(PresenterInterface $presenter)
    {
        $this->presenter = $presenter;
    }

    /**
     * @param string $name
     * @param mixed  $subject
     * @param array  $arguments
     *
     * @return bool
     */
    public function supports($name, $subject, array $arguments)
    {
        return
            (is_array($subject) || $subject instanceof \ArrayAccess) &&
            'haveKeyWithValue' === $name &&
            2 == count($arguments)
        ;
    }

    /**
     * @param ArrayAccess|array $subject
     * @param array $arguments
     *
     * @return bool
     */
    protected function matches($subject, array $arguments)
    {
        $key = $arguments[0];
        $value  = $arguments[1];

        if ($subject instanceof ArrayAccess) {
            return $subject->offsetExists($key) && $subject->offsetGet($key) === $value;
        }

        return (isset($subject[$key]) || array_key_exists($arguments[0], $subject)) && $subject[$key] === $value;
    }

    /**
     * @param string $name
     * @param mixed  $subject
     * @param array  $arguments
     *
     * @return FailureException
     */
    protected function getFailureException($name, $subject, array $arguments)
    {
        $key = $arguments[0];

        if (!$this->offsetExists($key, $subject)) {
            return new FailureException(sprintf('Expected %s to have key %s, but it didn\'t.',
                $this->presenter->presentValue($subject),
                $this->presenter->presentString($key)
            ));
        }

        return new FailureException(sprintf(
            'Expected %s to have value %s for %s key, but found %s.',
            $this->presenter->presentValue($subject),
            $this->presenter->presentValue($arguments[1]),
            $this->presenter->presentString($key),
            $this->presenter->presentValue($subject[$key])
        ));
    }

    /**
     * @param string $name
     * @param mixed  $subject
     * @param array  $arguments
     *
     * @return FailureException
     */
    protected function getNegativeFailureException($name, $subject, array $arguments)
    {
        return new FailureException(sprintf(
            'Expected %s not to have %s key, but it does.',
            $this->presenter->presentValue($subject),
            $this->presenter->presentString($arguments[0])
        ));
    }

    private function offsetExists($key, $subject)
    {
        return ($subject instanceof ArrayAccess && $subject->offsetExists($key)) || array_key_exists($key, $subject);
    }
}
