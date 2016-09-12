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

namespace PhpSpec\Listener;

use PhpSpec\CodeGenerator\GeneratorManager;
use PhpSpec\Console\ConsoleIO;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Exception\Example\TypeFailureException;
use PhpSpec\Locator\ResourceManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class InvalidTypeListener implements EventSubscriberInterface
{
    private $io;
    private $generator;
    private $resources;

    private $methodsToImplement = [];

    public function __construct(ConsoleIO $io, GeneratorManager $generator, ResourceManager $resources)
    {
        $this->io = $io;
        $this->generator = $generator;
        $this->resources = $resources;
    }

    public static function getSubscribedEvents()
    {
        return [
            'afterExample' => ['afterExample', 10],
            'afterSuite' => ['afterSuite', -10],
        ];
    }

    public function afterExample(ExampleEvent $event)
    {
        if (null === $exception = $event->getException()) {
            return;
        }

        if (!$exception instanceof TypeFailureException) {
            return;
        }

        $typeClassName = $exception->getExpectedType();

        if (!interface_exists($typeClassName)) {
            return;
        }

        $subjectClassName = get_class($exception->getSubject());
        $reflection = new \ReflectionClass($typeClassName);

        if (count($reflection->getMethods())) {
            $this->methodsToImplement[$typeClassName] = [];
        }

        /** @var \ReflectionMethod $method */
        foreach ($reflection->getMethods() as $method) {
            $this->methodsToImplement[$subjectClassName . '::' . $method->getName()] = $method->getParameters();
        }
    }

    public function afterSuite(SuiteEvent $event)
    {
        if (!$this->io->isCodeGenerationEnabled()) {
            return;
        }

        foreach ($this->methodsToImplement as $type => $calls) {

            $message = sprintf('Do you want me to implement the methods in `%s` for you?', $type);
            if ($this->io->askConfirmation($message)) {
                foreach ($calls as $call => $arguments) {
                    list($className, $methodName) = explode('::', $call);

                    try {
                        $resource = $this->resources->createResource($className);
                    } catch (\RuntimeException $e) {
                        continue;
                    }

                    $this->generator->generate($resource, 'method', array(
                        'name'      => $methodName,
                        'arguments' => $arguments
                    ));

                    $event->markAsWorthRerunning();
                }
            }
        }
    }
}
