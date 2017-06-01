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

use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Formatter\Presenter\Presenter;

final class TraversableThrowMatcher implements Matcher
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
        return 'throwWhenIterating' === $name
            && $subject instanceof \Traversable
            ;
    }

    private function loop($subject)
    {
        $exceptionThrown = null;
        try {
            foreach ($subject as $item) {
                continue;
            }
        } catch (\Exception $e) {
            $exceptionThrown = $e;
        } catch (\Throwable $e) {
            $exceptionThrown = $e;
        }
        return $exceptionThrown;
    }

    /**
     * @inheritdoc
     */
    public function positiveMatch($name, $subject, array $arguments)
    {
        if (empty($arguments)) {
            $arguments = [
                '\\Exception',
                '\\Throwable',
            ];
        }
        $exceptionThrown = $this->loop($subject);
        if (null === $exceptionThrown) {
            throw new FailureException(sprintf('Expected to get %s thrown, none got.', implode(' / ', array_map(function ($class) {
                return ltrim($class, '\\');
            }, $arguments))));
        } else {
            foreach ($arguments as $exceptionClass) {
                if (is_a($exceptionThrown, $exceptionClass)) {
                    return;
                }
            }
            throw new FailureException(sprintf('Expected to get %s thrown, got %s.', implode(' / ', array_map(function ($class) {
                return ltrim($class, '\\');
            }, $arguments)), get_class($exceptionThrown)));
        }
    }

    /**
     * @inheritdoc
     */
    public function negativeMatch($name, $subject, array $arguments)
    {

        $exceptionThrown = $this->loop($subject);
        if (null !== $exceptionThrown) {
            if (empty($arguments)) {
                throw new FailureException(sprintf('Expected nothing to be thrown, got %s.', get_class($exceptionThrown)));
            } else {
                foreach ($arguments as $exceptionClass) {
                    if (is_a($exceptionThrown, $exceptionClass)) {
                        throw new FailureException(sprintf('Expected %s to be not thrown, and it was.', implode(' / ', array_map(function ($class) {
                            return ltrim($class, '\\');
                        }, $arguments))));
                    }
                }
            }
        }
    }

    public function getPriority()
    {
        return 100;
    }
}
