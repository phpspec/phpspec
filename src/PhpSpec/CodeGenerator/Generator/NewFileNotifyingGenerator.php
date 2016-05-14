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

    /**
     * @param Resource $resource
     * @param string $generation
     * @param array $data
     *
     * @return bool
     */
    public function supports(Resource $resource, $generation, array $data)
    {
        return $this->generator->supports($resource, $generation, $data);
    }

    /**
     * @param Resource $resource
     * @param array $data
     */
    public function generate(Resource $resource, array $data)
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
     * @param Resource $resource
     * @return string
     */
    private function getFilePath(Resource $resource)
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
