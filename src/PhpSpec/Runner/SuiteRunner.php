<?php

namespace PhpSpec\Runner;

use PhpSpec\Event\SuiteEvent,
    PhpSpec\Exception\Example\StopOnFailureException,
    PhpSpec\Loader\Suite,
    PhpSpec\Runner\SpecificationRunner;

use PhpSpec\Loader\ResourceLoader;
use Symfony\Component\EventDispatcher\EventDispatcher;

class SuiteRunner
{
    private $dispatcher;
    private $specRunner;
    private $loader;

    public function __construct(EventDispatcher $dispatcher, SpecificationRunner $specRunner, ResourceLoader $loader)
    {
        $this->dispatcher = $dispatcher;
        $this->specRunner = $specRunner;
        $this->loader = $loader;
    }

    public function run($locator, $linenum)
    {
        $suite = $this->loader->load($locator, $linenum);

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
