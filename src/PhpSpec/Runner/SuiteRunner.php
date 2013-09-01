<?php

namespace PhpSpec\Runner;

use PhpSpec\Event\SuiteEvent,
    PhpSpec\Exception\Example\StopOnFailureException,
    PhpSpec\Loader\Suite,
    PhpSpec\Runner\SpecificationRunner;

use Symfony\Component\EventDispatcher\EventDispatcher;

class SuiteRunner
{
    private $dispatcher;
    private $specRunner;

    public function __construct(EventDispatcher $dispatcher, SpecificationRunner $specRunner)
    {
        $this->dispatcher = $dispatcher;
        $this->specRunner = $specRunner;
    }

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
