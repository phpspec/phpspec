<?php

namespace PhpSpec\CodeGenerator\Generator;

use PhpSpec\Event\FileCreationEvent;
use PhpSpec\Locator\Resource;
use PhpSpec\Util\Filesystem;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class NewFileNotifyingGenerator implements Generator
{
    /**
     * @var Generator
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
     * @param Generator $generator
     * @param EventDispatcherInterface $dispatcher
     * @param Filesystem $filesystem
     */
    public function __construct(Generator $generator, EventDispatcherInterface $dispatcher, Filesystem $filesystem)
    {
        $this->generator = $generator;
        $this->dispatcher = $dispatcher;
        $this->filesystem = $filesystem;
    }

    public function supports(Resource $resource, string $generation, array $data) : bool
    {
        return $this->generator->supports($resource, $generation, $data);
    }

    public function generate(Resource $resource, array $data)
    {
        $filePath = $this->getFilePath($resource);

        $fileExisted = $this->fileExists($filePath);

        $this->generator->generate($resource, $data);

        $this->dispatchEventIfFileWasCreated($fileExisted, $filePath);
    }

    public function getPriority() : int
    {
        return $this->generator->getPriority();
    }

    private function getFilePath(Resource $resource) : string
    {
        if ($this->generator->supports($resource, 'specification', array())) {
            return $resource->getSpecFilename();
        }

        return $resource->getSrcFilename();
    }

    private function fileExists(string $filePath) : bool
    {
        return $this->filesystem->pathExists($filePath);
    }

    private function dispatchEventIfFileWasCreated(bool $fileExisted, string $filePath)
    {
        if (!$fileExisted && $this->fileExists($filePath)) {
            $event = new FileCreationEvent($filePath);
            $this->dispatcher->dispatch('afterFileCreation', $event);
        }
    }
}
