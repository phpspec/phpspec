<?php

namespace PhpSpec\Runner;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use PhpSpec\Event;
use PhpSpec\Loader\Node\SpecificationNode;

class SpecificationRunner
{
    private $dispatcher;
    private $exampleRunner;

    public function __construct(EventDispatcherInterface $dispatcher, ExampleRunner $exampleRunner)
    {
        $this->dispatcher    = $dispatcher;
        $this->exampleRunner = $exampleRunner;
    }

    public function run(SpecificationNode $specification)
    {
        $startTime = microtime(true);
        $this->dispatcher->dispatch('beforeSpecification',
            new Event\SpecificationEvent($specification)
        );

        $result = Event\ExampleEvent::PASSED;
        foreach ($specification->getExamples() as $example) {
            $result = max($result, $this->exampleRunner->run($example));
        }

        $this->dispatcher->dispatch('afterSpecification',
            new Event\SpecificationEvent($specification, microtime(true) - $startTime, $result)
        );

        return $result;
    }
}
