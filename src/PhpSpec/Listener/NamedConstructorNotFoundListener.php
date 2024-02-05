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
use PhpSpec\Exception\Fracture\NamedConstructorNotFoundException;
use PhpSpec\Locator\ResourceManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class NamedConstructorNotFoundListener implements EventSubscriberInterface
{
    /** @var array<class-string, array<string, array>> */
    private array $methods = [];

    public function __construct(
        private ConsoleIO $io,
        private ResourceManager $resources,
        private GeneratorManager $generator
    )
    {
    }

    public static function getSubscribedEvents() : array
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

        if (!$exception instanceof NamedConstructorNotFoundException) {
            return;
        }

        $className = \get_class($exception->getSubject());
        $this->methods[$className][$exception->getMethodName()] = $exception->getArguments();
    }

    public function afterSuite(SuiteEvent $event): void
    {
        if (!$this->io->isCodeGenerationEnabled()) {
            return;
        }

        foreach ($this->methods as $classname => $methods) {
            foreach ($methods as $method => $arguments) {
                $message = sprintf('Do you want me to create `%s::%s()` for you?', $classname, $method);

                try {
                    $resource = $this->resources->createResource($classname);
                } catch (\RuntimeException $e) {
                    continue;
                }

                if ($this->io->askConfirmation($message)) {
                    $this->generator->generate($resource, 'named_constructor', array(
                        'name' => $method,
                        'arguments' => $arguments
                    ));
                    $event->markAsWorthRerunning();

                    if (!method_exists($classname, '__construct')) {
                        $this->generator->generate($resource, 'private-constructor', array(
                            'name' => $method,
                            'arguments' => $arguments
                        ));
                    }
                }
            }
        }
    }
}
