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

use PhpSpec\Util\NameChecker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use PhpSpec\Console\ConsoleIO;
use PhpSpec\Locator\ResourceManager;
use PhpSpec\CodeGenerator\GeneratorManager;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Exception\Fracture\MethodNotFoundException;

final class MethodNotFoundListener implements EventSubscriberInterface
{
    private array $methods = array();
    private array $wrongMethodNames = array();

    
    public function __construct(private ConsoleIO $io, private ResourceManager $resources, private GeneratorManager $generator, private NameChecker $nameChecker)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return array(
            'afterExample' => array('afterExample', 10),
            'afterSuite'   => array('afterSuite', -10),
        );
    }

    public function afterExample(ExampleEvent $event): void
    {
        if (null === $exception = $event->getException()) {
            return;
        }

        if (!$exception instanceof MethodNotFoundException) {
            return;
        }

        $classname = $exception->getSubject()::class;
        $methodName = $exception->getMethodName();
        $this->methods[$classname .'::'.$methodName] = $exception->getArguments();
        $this->checkIfMethodNameAllowed($methodName);
    }

    public function afterSuite(SuiteEvent $event): void
    {
        if (!$this->io->isCodeGenerationEnabled()) {
            return;
        }

        foreach ($this->methods as $call => $arguments) {
            [$classname, $method] = explode('::', $call);

            if (\in_array($method, $this->wrongMethodNames)) {
                continue;
            }

            $message = sprintf('Do you want me to create `%s()` for you?', $call);

            try {
                $resource = $this->resources->createResource($classname);
            } catch (\RuntimeException) {
                continue;
            }

            if ($this->io->askConfirmation($message)) {
                $this->generator->generate($resource, 'method', array(
                    'name'      => $method,
                    'arguments' => $arguments
                ));
                $event->markAsWorthRerunning();
            }
        }

        if ($this->wrongMethodNames) {
            $this->writeWrongMethodNameMessage();
            $event->markAsNotWorthRerunning();
        }
    }

    private function checkIfMethodNameAllowed(string $methodName): void
    {
        if (!$this->nameChecker->isNameValid($methodName)) {
            $this->wrongMethodNames[] = $methodName;
        }
    }

    private function writeWrongMethodNameMessage(): void
    {
        foreach ($this->wrongMethodNames as $methodName) {
            $message = sprintf("I cannot generate the method '%s' for you because it is a reserved keyword", $methodName);
            $this->io->writeBrokenCodeBlock($message, 2);
        }
    }
}
