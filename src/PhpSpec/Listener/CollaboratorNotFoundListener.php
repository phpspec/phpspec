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
use PhpSpec\Console\IO;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Exception\Fracture\CollaboratorNotFoundException;
use PhpSpec\Locator\ResourceInterface;
use PhpSpec\Locator\ResourceManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CollaboratorNotFoundListener implements EventSubscriberInterface
{
    /**
     * @var IO
     */
    private $io;

    /**
     * @var CollaboratorNotFoundException[]
     */
    private $exceptions = array();

    /**
     * @var ResourceManagerInterface
     */
    private $resources;

    /**
     * @var GeneratorManager
     */
    private $generator;

    /**
     * @param IO $io
     * @param ResourceManagerInterface $resources
     * @param GeneratorManager $generator
     */
    public function __construct(IO $io, ResourceManagerInterface $resources, GeneratorManager $generator)
    {
        $this->io = $io;
        $this->resources = $resources;
        $this->generator = $generator;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            'afterExample' => array('afterExample', 10),
            'afterSuite'   => array('afterSuite', -10)
        );
    }

    /**
     * @param ExampleEvent $event
     */
    public function afterExample(ExampleEvent $event)
    {
        if (($exception = $event->getException()) &&
            ($exception instanceof CollaboratorNotFoundException)) {
            $this->exceptions[$exception->getCollaboratorName()] = $exception;
        }
    }

    /**
     * @param SuiteEvent $event
     */
    public function afterSuite(SuiteEvent $event)
    {
        if (!$this->io->isCodeGenerationEnabled()) {
            return;
        }

        foreach ($this->exceptions as $exception) {
            $resource = $this->resources->createResource($exception->getCollaboratorName());

            if ($this->resourceIsInSpecNamespace($exception, $resource)) {
                continue;
            }

            if ($this->io->askConfirmation(
                sprintf('Would you like me to generate an interface `%s` for you?', $exception->getCollaboratorName())
            )) {
                $this->generator->generate($resource, 'interface');
                $event->markAsWorthRerunning();
            }
        }
    }

    /**
     * @param CollaboratorNotFoundException $exception
     * @param ResourceInterface $resource
     * @return bool
     */
    private function resourceIsInSpecNamespace($exception, $resource)
    {
        return strpos($exception->getCollaboratorName(), $resource->getSpecNamespace()) === 0;
    }
}
