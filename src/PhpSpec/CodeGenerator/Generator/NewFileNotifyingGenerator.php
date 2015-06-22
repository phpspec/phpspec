<?php

namespace PhpSpec\CodeGenerator\Generator;

use PhpSpec\Event\FileCreationEvent;
use PhpSpec\Locator\ResourceInterface;
use PhpSpec\Util\Filesystem;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class NewFileNotifyingGenerator implements GeneratorInterface
{
    /**
     * @var GeneratorInterface
     */
    private $generator;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @param GeneratorInterface $generator
     * @param EventDispatcherInterface $dispatcher
     * @param Filesystem $filesystem
     */
    public function __construct(GeneratorInterface $generator, EventDispatcherInterface $dispatcher, Filesystem $filesystem = null)
    {
        $this->generator = $generator;
        $this->dispatcher = $dispatcher;
        $this->filesystem = $filesystem ?: new Filesystem();
    }

    /**
     * @param ResourceInterface $resource
     * @param string $generation
     * @param array $data
     *
     * @return bool
     */
    public function supports(ResourceInterface $resource, $generation, array $data)
    {
        return $this->generator->supports($resource, $generation, $data);
    }

    /**
     * @param ResourceInterface $resource
     * @param array $data
     */
    public function generate(ResourceInterface $resource, array $data)
    {
        $filePath = $this->getFilePath($resource);

        $fileExisted = $this->fileExists($filePath);

        $this->generator->generate($resource, $data);

        $this->dispatchEventIfFileWasCreated($fileExisted, $filePath);
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->generator->getPriority();
    }

    /**
     * @param ResourceInterface $resource
     * @return string
     */
    private function getFilePath(ResourceInterface $resource)
    {
        if ($this->generator->supports($resource, 'specification', array())) {
            return $resource->getSpecFilename();
        }

        return $resource->getSrcFilename();
    }

    /**
     * @param string $filePath
     * @return bool
     */
    private function fileExists($filePath)
    {
        return $this->filesystem->pathExists($filePath);
    }

    /**
     * @param bool $fileExisted
     * @param string $filePath
     */
    private function dispatchEventIfFileWasCreated($fileExisted, $filePath)
    {
        if (!$fileExisted && $this->fileExists($filePath)) {
            $event = new FileCreationEvent($filePath);
            $this->dispatcher->dispatch('afterFileCreation', $event);
        }
    }
}
