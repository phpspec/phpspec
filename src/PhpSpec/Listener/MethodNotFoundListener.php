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

use PhpSpec\Util\VoterInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use PhpSpec\Console\IO;
use PhpSpec\Locator\ResourceManagerInterface;
use PhpSpec\CodeGenerator\GeneratorManager;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Exception\Fracture\MethodNotFoundException;

class MethodNotFoundListener implements EventSubscriberInterface
{
    private $io;
    private $resources;
    private $generator;
    private $methods = array();
    private $wrongMethodNames = array();
    /**
     * @var VoterInterface
     */
    private $nameChecker;

    /**
     * @param IO $io
     * @param ResourceManagerInterface $resources
     * @param GeneratorManager $generator
     * @param VoterInterface $nameChecker
     */
    public function __construct(
        IO $io,
        ResourceManagerInterface $resources,
        GeneratorManager $generator,
        VoterInterface $nameChecker
    ) {
        $this->io        = $io;
        $this->resources = $resources;
        $this->generator = $generator;
        $this->nameChecker = $nameChecker;
    }

    public static function getSubscribedEvents()
    {
        return array(
            'afterExample' => array('afterExample', 10),
            'afterSuite'   => array('afterSuite', -10),
        );
    }

    public function afterExample(ExampleEvent $event)
    {
        if (null === $exception = $event->getException()) {
            return;
        }

        if (!$exception instanceof MethodNotFoundException) {
            return;
        }

        $classname = get_class($exception->getSubject());
        $methodName = $exception->getMethodName();
        $this->methods[$classname .'::'.$methodName] = $exception->getArguments();
        $this->checkIfMethodNameAllowed($methodName);
    }

    public function afterSuite(SuiteEvent $event)
    {
        if (!$this->io->isCodeGenerationEnabled()) {
            return;
        }

        if (!empty($this->wrongMethodNames)) {
            $this->writeErrorMessage();
            return;
        }

        foreach ($this->methods as $call => $arguments) {
            list($classname, $method) = explode('::', $call);
            $message = sprintf('Do you want me to create `%s()` for you?', $call);

            try {
                $resource = $this->resources->createResource($classname);
            } catch (\RuntimeException $e) {
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
    }

    private function checkIfMethodNameAllowed($methodName)
    {
        if (!$this->nameChecker->supports($methodName)) {
            $this->wrongMethodNames[] = $methodName;
        }
    }

    private function writeErrorMessage()
    {
        foreach ($this->wrongMethodNames as $methodName) {
            $message = sprintf("You cannot use restricted `%s` as a method name", $methodName);
            $this->io->writeError($message, 2);
        }
    }
}
