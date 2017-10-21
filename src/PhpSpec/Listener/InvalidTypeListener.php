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

use PhpSpec\CodeGenerator\Generator\Argument\Factory as ArgumentFactory;
use PhpSpec\CodeGenerator\GeneratorManager;
use PhpSpec\Console\ConsoleIO;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Exception\Example\TypeFailureException;
use PhpSpec\Locator\Resource;
use PhpSpec\Locator\ResourceManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class InvalidTypeListener implements EventSubscriberInterface
{
    private $io;
    private $generator;
    private $resources;
    private $argumentFactory;

    private $methodsToImplement = [];

    public function __construct(ConsoleIO $io, GeneratorManager $generator, ResourceManager $resources, ArgumentFactory $argumentFactory)
    {
        $this->io = $io;
        $this->generator = $generator;
        $this->resources = $resources;
        $this->argumentFactory = $argumentFactory;
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

        $this->storeMethodsToImplement($exception->getSubject(), $typeClassName);
    }

    public function afterSuite(SuiteEvent $event)
    {
        if (!$this->io->isCodeGenerationEnabled()) {
            return;
        }

        foreach ($this->methodsToImplement as $class => $types) {
            try {
                $classResource = $this->resources->createResource($class);
            } catch (\RuntimeException $e) {
                continue;
            }

            $this->promptUserToImplementMethods($event, $types, $class, $classResource);
        }
    }

    private function storeMethodsToImplement($subject, $typeClassName)
    {
        $subjectClassName = get_class($subject);
        $reflection = new \ReflectionClass($typeClassName);

        if (count($reflection->getMethods())) {
            $this->methodsToImplement[$subjectClassName][$typeClassName] = [];
        }

        /** @var \ReflectionMethod $method */
        foreach ($reflection->getMethods() as $method) {
            $this->methodsToImplement[$subjectClassName][$typeClassName][$method->getName()] = $method->getParameters();
        }
    }

    private function promptUserToImplementMethods(SuiteEvent $event, array $typesAndMethods, string $class, Resource $classResource)
    {
        foreach ($typesAndMethods as $type => $methods) {
            $message = sprintf('Do you want me to implement the methods from `%s` in `%s` for you?', $type, $class);

            if ($this->io->askConfirmation($message)) {
                $this->generator->generate($classResource, 'implements', ['interface' => $type]);

                foreach ($methods as $method => $parameters) {
                    $this->generator->generate($classResource, 'method', array(
                        'name' => $method,
                        'arguments' => $this->argumentFactory->fromReflectionParams($parameters)
                    ));

                    $event->markAsWorthRerunning();
                }
            }
        }
    }
}
