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
use PhpSpec\Exception\Locator\ResourceCreationException;
use PhpSpec\Locator\ResourceManager;
use PhpSpec\Util\NameChecker;
use Prophecy\Argument\ArgumentsWildcard;
use Prophecy\Exception\Doubler\MethodNotFoundException;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class CollaboratorMethodNotFoundListener implements EventSubscriberInterface
{
    const PROMPT = 'Would you like me to generate a method signature `%s::%s()` for you?';

    private array $interfaces = [];

    private array $wrongMethodNames = [];

    public function __construct(
        private ConsoleIO $io,
        private ResourceManager $resources,
        private GeneratorManager $generator,
        private NameChecker $nameChecker
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return array(
            'afterExample' => array('afterExample', 10),
            'afterSuite' => array('afterSuite', -10)
        );
    }

    public function afterExample(ExampleEvent $event): void
    {
        if (!$exception = $this->getMethodNotFoundException($event)) {
            return;
        }

        $className = $exception->getClassName();

        // Prophecy sometimes throws the exception with the Prophecy rather than the FCQN - in these cases we need to parse the error
        if ($className instanceof ObjectProphecy) {

            $classPattern = '[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*'; //from https://www.php.net/manual/en/language.oop5.basic.php
            $fcqnPattern = "(?:$classPattern)(?:\\\\$classPattern)*)";
            $method = preg_quote($exception->getMethodName());

            if(preg_match("/(?<fcqn>$fcqnPattern::$method\(/", $exception->getMessage(), $matches)) {
                $className = $matches['fcqn'];
            }
        }

        if (!$interface = $this->getDoubledInterface($className)) {
            return;
        }

        if (!array_key_exists($interface, $this->interfaces)) {
            $this->interfaces[$interface] = array();
        }

        $methodName = $exception->getMethodName();
        $this->interfaces[$interface][$methodName] = $exception->getArguments();
        $this->checkIfMethodNameAllowed($methodName);
    }

    private function getDoubledInterface(mixed $class) : mixed
    {
        if (class_parents($class) !== array(\stdClass::class=>\stdClass::class)) {
            return null;
        }

        $interfaces = array_filter(class_implements($class),
            function (string $interface) {
                return !preg_match('/^Prophecy/', $interface);
            }
        );

        if (\count($interfaces) !== 1) {
            return null;
        }

        return current($interfaces);
    }

    public function afterSuite(SuiteEvent $event): void
    {
        foreach ($this->interfaces as $interface => $methods) {
            try {
                $resource = $this->resources->createResource($interface);
            } catch (ResourceCreationException $e) {
                continue;
            }

            foreach ($methods as $method => $arguments) {
                if (\in_array($method, $this->wrongMethodNames)) {
                    continue;
                }

                if ($this->io->askConfirmation(sprintf(self::PROMPT, $interface, $method))) {
                    $this->generator->generate(
                        $resource,
                        'method-signature',
                        array(
                            'name' => $method,
                            'arguments' => $this->getRealArguments($arguments)
                        )
                    );
                    $event->markAsWorthRerunning();
                }
            }
        }

        if ($this->wrongMethodNames) {
            $this->writeErrorMessage();
            $event->markAsNotWorthRerunning();
        }
    }

    private function getRealArguments(mixed $prophecyArguments): array
    {
        if ($prophecyArguments instanceof ArgumentsWildcard) {
            return $prophecyArguments->getTokens();
        }

        return array();
    }

    private function getMethodNotFoundException(ExampleEvent $event) : ?MethodNotFoundException
    {
        if ($this->io->isCodeGenerationEnabled()
            && ($exception = $event->getException())
            && $exception instanceof MethodNotFoundException) {
            return $exception;
        }

        return null;
    }

    private function checkIfMethodNameAllowed(string $methodName): void
    {
        if (!$this->nameChecker->isNameValid($methodName)) {
            $this->wrongMethodNames[] = $methodName;
        }
    }

    private function writeErrorMessage(): void
    {
        foreach ($this->wrongMethodNames as $methodName) {
            $message = sprintf("I cannot generate the method '%s' for you because it is a reserved keyword", $methodName);
            $this->io->writeBrokenCodeBlock($message, 2);
        }
    }
}
