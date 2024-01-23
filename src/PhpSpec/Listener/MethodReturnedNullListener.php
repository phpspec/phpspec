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
use PhpSpec\Event\MethodCallEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Exception\Example\MethodFailureException;
use PhpSpec\Exception\Example\NotEqualException;
use PhpSpec\Locator\ResourceManager;
use PhpSpec\Util\MethodAnalyser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class MethodReturnedNullListener implements EventSubscriberInterface
{
    private array $nullMethods = [];

    private ?MethodCallEvent $lastMethodCallEvent = null;

    public function __construct(
        private ConsoleIO $io,
        private ResourceManager $resources,
        private GeneratorManager $generator,
        private MethodAnalyser $methodAnalyser
    )
    {
    }

    public static function getSubscribedEvents() : array
    {
        return array(
            'afterExample' => array('afterExample', 10),
            'afterSuite'   => array('afterSuite', -20),
            'afterMethodCall' => array('afterMethodCall')
        );
    }

    public function afterMethodCall(MethodCallEvent $methodCallEvent): void
    {
        $this->lastMethodCallEvent = $methodCallEvent;
    }

    public function afterExample(ExampleEvent $exampleEvent): void
    {
        $exception = $exampleEvent->getException();

        if (!$exception instanceof NotEqualException) {
            return;
        }

        if ($exception->getActual() !== null) {
            return;
        }

        if (\is_object($exception->getExpected())
         || \is_array($exception->getExpected())
         || \is_resource($exception->getExpected())
        ) {
            return;
        }

        if (!$this->lastMethodCallEvent) {

            if (!$exception instanceof MethodFailureException) {
                return;
            }

            $subject = $exception->getSubject();
            $method = $exception->getMethod();
            if (is_null($subject)) {
                return;
            }
            $class = \get_class($subject);
        } else {
            $class = \get_class($this->lastMethodCallEvent->getSubject());
            $method = $this->lastMethodCallEvent->getMethod();
        }

        if (!$this->methodAnalyser->methodIsEmpty($class, $method)) {
            return;
        }

        $key = $class.'::'.$method;

        if (!array_key_exists($key, $this->nullMethods)) {
            $this->nullMethods[$key] = array(
                'class' => $this->methodAnalyser->getMethodOwnerName($class, $method),
                'method' => $method,
                'expected' => array()
            );
        }

        $this->nullMethods[$key]['expected'][] = $exception->getExpected();
    }

    public function afterSuite(SuiteEvent $event): void
    {
        if (!$this->io->isCodeGenerationEnabled()) {
            return;
        }

        if (!$this->io->isFakingEnabled()) {
            return;
        }

        foreach ($this->nullMethods as $methodString => $failedCall) {
            $failedCall['expected'] = array_unique($failedCall['expected']);

            if (\count($failedCall['expected'])>1) {
                continue;
            }

            $expected = current($failedCall['expected']);
            $class = $failedCall['class'];

            $message = sprintf(
                'Do you want me to make `%s()` always return %s for you?',
                $methodString,
                var_export($expected, true)
            );

            try {
                $resource = $this->resources->createResource($class);
            } catch (\RuntimeException $exception) {
                continue;
            }

            if ($this->io->askConfirmation($message)) {
                $this->generator->generate(
                    $resource,
                    'returnConstant',
                    array('method' => $failedCall['method'], 'expected' => $expected)
                );
                $event->markAsWorthRerunning();
            }
        }
    }
}
