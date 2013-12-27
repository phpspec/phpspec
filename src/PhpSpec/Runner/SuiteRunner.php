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

namespace PhpSpec\Runner;

use PhpSpec\Event\SuiteEvent,
    PhpSpec\Exception\Example\StopOnFailureException,
    PhpSpec\Loader\Suite,
    PhpSpec\Runner\SpecificationRunner;

use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Class SuiteRunner
 * @package PhpSpec\Runner
 */
class SuiteRunner
{
    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcher
     */
    private $dispatcher;
    /**
     * @var SpecificationRunner
     */
    private $specRunner;

    /**
     * @param EventDispatcher $dispatcher
     * @param SpecificationRunner $specRunner
     */
    public function __construct(EventDispatcher $dispatcher, SpecificationRunner $specRunner)
    {
        $this->dispatcher = $dispatcher;
        $this->specRunner = $specRunner;
    }

    /**
     * @param Suite $suite
     * @return int|mixed
     */
    public function run(Suite $suite)
    {
        $this->dispatcher->dispatch('beforeSuite', new SuiteEvent($suite));

        $result = 0;
        $startTime = microtime(true);
        
        foreach ($suite->getSpecifications() as $specification) {
            try {
                $result = max($result, $this->specRunner->run($specification));
            } catch (StopOnFailureException $e) {
                break;
            }
        }

        $endTime = microtime(true);
        $this->dispatcher->dispatch('afterSuite', 
            new SuiteEvent($suite, $endTime-$startTime, $result)
        );

        return $result;
    }
}
