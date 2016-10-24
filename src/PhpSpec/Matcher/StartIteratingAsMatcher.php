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
use PhpSpec\Matcher\Iterate\IterablesMatcher;

final class StartIteratingAsMatcher implements Matcher
{
    /**
     * @var Presenter
     */
    private $presenter;

    /**
     * @var IterablesMatcher
     */
    private $iterablesMatcher;

    /**
     * @param Presenter $presenter
     */
    public function __construct(Presenter $presenter)
    {
        $this->presenter = $presenter;
        $this->iterablesMatcher = new IterablesMatcher();
    }

    /**
     * {@inheritdoc}
     */
    public function supports($name, $subject, array $arguments)
    {
        return 'startIteratingAs' === $name
            && 1 === count($arguments)
            && ($subject instanceof \Traversable || is_array($subject))
            && ($arguments[0] instanceof \Traversable || is_array($arguments[0]))
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function positiveMatch($name, $subject, array $arguments)
    {
        try {
            $this->iterablesMatcher->match($subject, $arguments[0]);
        } catch (Iterate\SubjectHasMoreElementsException $exception) {
            // everything's all right
        } catch (Iterate\SubjectHasFewerElementsException $exception) {
            throw new FailureException('Expected subject to have the same or more elements than matched value, but it has fewer.', 0, $exception);
        } catch (Iterate\SubjectElementDoesNotMatchException $exception) {
            throw new FailureException(sprintf(
                'Expected subject to have element #%d with key %s and value %s, but got key %s and value %s.',
                $exception->getElementNumber(),
                $this->presenter->presentValue($exception->getExpectedKey()),
                $this->presenter->presentValue($exception->getExpectedValue()),
                $this->presenter->presentValue($exception->getSubjectKey()),
                $this->presenter->presentValue($exception->getSubjectValue())
            ), 0, $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function negativeMatch($name, $subject, array $arguments)
    {
        try {
            $this->positiveMatch($name, $subject, $arguments);
        } catch (FailureException $exception) {
            return;
        }

        throw new FailureException('Expected subject not to start iterating the same as matched value, but it does.');
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        return 100;
    }
}
