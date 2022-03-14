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

use PhpSpec\Event\SpecificationEvent;
use PhpSpec\Event\ExampleEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use PhpSpec\Event;
use PhpSpec\Loader\Node\SpecificationNode;

class SpecificationRunner
{
    private EventDispatcherInterface $dispatcher;
    private ExampleRunner $exampleRunner;

    
    public function __construct(EventDispatcherInterface $dispatcher, ExampleRunner $exampleRunner)
    {
        $this->dispatcher    = $dispatcher;
        $this->exampleRunner = $exampleRunner;
    }

    /**
     * @return int
     */
    public function run(SpecificationNode $specification): int
    {
        $startTime = microtime(true);
        $this->dispatcher->dispatch(
            new SpecificationEvent($specification),
            'beforeSpecification'
        );

        $result = ExampleEvent::PASSED;

        try {
            foreach ($specification->getExamples() as $example) {
                $result = max($result, $this->exampleRunner->run($example));
            }
        } finally {
            $this->dispatcher->dispatch(
                new SpecificationEvent($specification, microtime(true) - $startTime, $result),
                'afterSpecification'
            );
        }

        return $result;
    }
}
