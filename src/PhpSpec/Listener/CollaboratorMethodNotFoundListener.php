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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class CollaboratorMethodNotFoundListener implements EventSubscriberInterface
{
    const PROMPT = 'Would you like me to generate a method signature `%s::%s()` for you?';

    /**
     * @var ConsoleIO
     */
    private $io;

    /**
     * @var array
     */
    private $interfaces = array();

    /**
     * @var ResourceManager
     */
    private $resources;

    /**
     * @var GeneratorManager
     */
    private $generator;

    /**
     * @var NameChecker
     */
    private $nameChecker;

    /**
     * @var array
     */
    private $wrongMethodNames = array();

    /**
     * @param ConsoleIO $io
     * @param ResourceManager $resources
     * @param GeneratorManager $generator
     * @param NameChecker $nameChecker
     */
    public function __construct(
        ConsoleIO $io,
        ResourceManager $resources,
        GeneratorManager $generator,
        NameChecker $nameChecker
    ) {
        $this->io = $io;
        $this->resources = $resources;
        $this->generator = $generator;
        $this->nameChecker = $nameChecker;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            'afterExample' => array('afterExample', 10),
            'afterSuite' => array('afterSuite', -10)
        );
    }

    /**
     * @param ExampleEvent $event
     */
    public function afterExample(ExampleEvent $event)
    {
        if (!$exception = $this->getMethodNotFoundException($event)) {
            return;
        }

        if (!$interface = $this->getDoubledInterface($exception->getClassName())) {
            return;
        }

        if (!array_key_exists($interface, $this->interfaces)) {
            $this->interfaces[$interface] = array();
        }

        $methodName = $exception->getMethodName();
        $this->interfaces[$interface][$methodName] = $exception->getArguments();
        $this->checkIfMethodNameAllowed($methodName);
    }

    /**
     * @param string $classname
     * @return mixed
     */
    private function getDoubledInterface($classname)
    {
        if (class_parents($classname) !== array('stdClass'=>'stdClass')) {
            return;
        }

        $interfaces = array_filter(class_implements($classname),
            function ($interface) {
                return !preg_match('/^Prophecy/', $interface);
            }
        );

        if (count($interfaces) !== 1) {
            return;
        }

        return current($interfaces);
    }

    /**
     * @param SuiteEvent $event
     */
    public function afterSuite(SuiteEvent $event)
    {
        foreach ($this->interfaces as $interface => $methods) {
            try {
                $resource = $this->resources->createResource($interface);
            } catch (ResourceCreationException $e) {
                continue;
            }

            foreach ($methods as $method => $arguments) {
                if (in_array($method, $this->wrongMethodNames)) {
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

    /**
     * @param mixed $prophecyArguments
     * @return array
     */
    private function getRealArguments($prophecyArguments)
    {
        if ($prophecyArguments instanceof ArgumentsWildcard) {
            return $prophecyArguments->getTokens();
        }

        return array();
    }

    /**
     * @param ExampleEvent $event
     * @return bool|\Exception
     */
    private function getMethodNotFoundException(ExampleEvent $event)
    {
        if ($this->io->isCodeGenerationEnabled()
            && ($exception = $event->getException())
            && $exception instanceof MethodNotFoundException) {
            return $exception;
        }
    }

    private function checkIfMethodNameAllowed($methodName)
    {
        if (!$this->nameChecker->isNameValid($methodName)) {
            $this->wrongMethodNames[] = $methodName;
        }
    }

    private function writeErrorMessage()
    {
        foreach ($this->wrongMethodNames as $methodName) {
            $message = sprintf("I cannot generate the method '%s' for you because it is a reserved keyword", $methodName);
            $this->io->writeBrokenCodeBlock($message, 2);
        }
    }
}
