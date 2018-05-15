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
use PhpSpec\Exception\Example\NotEqualException;
use PhpSpec\Formatter\Presenter\Presenter;

final class ApproximatelyMatcher extends BasicMatcher
{

    /**
     * @var array
     */
    private static $keywords = array(
        'beApproximately',
        'beEqualToApproximately',
        'equalApproximately',
        'returnApproximately'
    );

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
     * @param string $name
     * @param mixed $subject
     * @param array $arguments
     *
     * @return bool
     */
    public function supports(string $name, $subject, array $arguments): bool
    {
        return \in_array($name, self::$keywords) && 2 == \count($arguments);
    }

    /**
     * @param mixed $subject
     * @param array $arguments
     *
     * @return bool
     */
    protected function matches($subject, array $arguments): bool
    {
        $value = (float)$arguments[0];
        return (abs($subject - $value) < $arguments[1]);
    }


    protected function getFailureException(string $name, $subject, array $arguments): FailureException
    {
        return new FailureException(sprintf(
            'Expected an approximated value of %s, but got %s',
            $this->presenter->presentValue($arguments[0]),
            $this->presenter->presentValue($subject)
        ));
    }

    protected function getNegativeFailureException(string $name, $subject, array $arguments): FailureException
    {
        return new FailureException(sprintf(
            'Did Not expect an approximated value of %s, but got %s',
            $this->presenter->presentValue($arguments[0]),
            $this->presenter->presentValue($subject)
        ));
    }


}
