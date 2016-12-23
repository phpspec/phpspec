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

namespace PhpSpec\CodeGenerator\Generator;

use PhpSpec\Console\ConsoleIO;
use PhpSpec\CodeGenerator\TemplateRenderer;
use PhpSpec\Process\Context\ExecutionContext;
use PhpSpec\Util\Filesystem;
use PhpSpec\Locator\Resource;

/**
 * Base class with common behaviour for generating class and spec class
 */
abstract class PromptingGenerator implements Generator
{
    /**
     * @var ConsoleIO
     */
    private $io;

    /**
     * @var TemplateRenderer
     */
    private $templates;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var ExecutionContext
     */
    private $executionContext;

    /**
     * @param ConsoleIO $io
     * @param TemplateRenderer $templates
     * @param Filesystem $filesystem
     * @param ExecutionContext $executionContext
     */
    public function __construct(ConsoleIO $io, TemplateRenderer $templates, Filesystem $filesystem, ExecutionContext $executionContext)
    {
        $this->io         = $io;
        $this->templates  = $templates;
        $this->filesystem = $filesystem;
        $this->executionContext = $executionContext;
    }

    /**
     * @param Resource $resource
     * @param array             $data
     */
    public function generate(Resource $resource, array $data = array())
    {
        $filepath = $this->getFilePath($resource);

        if ($this->fileAlreadyExists($filepath)) {
            if ($this->userAborts($filepath)) {
                return;
            }

            $this->io->writeln();
        }

        $this->createDirectoryIfItDoesExist($filepath);
        $this->generateFileAndRenderTemplate($resource, $filepath);
        $this->executionContext->addGeneratedType($resource->getSrcClassname());
    }

    /**
     * @return TemplateRenderer
     */
    protected function getTemplateRenderer()
    {
        return $this->templates;
    }

    /**
     * @param Resource $resource
     *
     * @return string
     */
    abstract protected function getFilePath(Resource $resource);

    /**
     * @param Resource $resource
     * @param string            $filepath
     *
     * @return string
     */
    abstract protected function renderTemplate(Resource $resource, $filepath);

    /**
     * @param Resource $resource
     * @param string            $filepath
     *
     * @return string
     */
    abstract protected function getGeneratedMessage(Resource $resource, $filepath);

    /**
     * @param string $filepath
     *
     * @return bool
     */
    private function fileAlreadyExists($filepath)
    {
        return $this->filesystem->pathExists($filepath);
    }

    /**
     * @param string $filepath
     *
     * @return bool
     */
    private function userAborts($filepath)
    {
        $message = sprintf('File "%s" already exists. Overwrite?', basename($filepath));

        return !$this->io->askConfirmation($message, false);
    }

    /**
     * @param string $filepath
     */
    private function createDirectoryIfItDoesExist($filepath)
    {
        $path = dirname($filepath);
        if (!$this->filesystem->isDirectory($path)) {
            $this->filesystem->makeDirectory($path);
        }
    }

    /**
     * @param Resource $resource
     * @param string            $filepath
     */
    private function generateFileAndRenderTemplate(Resource $resource, $filepath)
    {
        $content = $this->renderTemplate($resource, $filepath);

        $this->filesystem->putFileContents($filepath, $content);
        $this->io->writeln($this->getGeneratedMessage($resource, $filepath));
    }
}
