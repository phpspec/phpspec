<?php

namespace PhpSpec\CodeGenerator\Generator;

use PhpSpec\Console\IO;
use PhpSpec\CodeGenerator\TemplateRenderer;
use PhpSpec\Util\Filesystem;
use PhpSpec\Locator\ResourceInterface;

abstract class FileGenerator implements GeneratorInterface
{
    protected $io;
    protected $templates;
    protected $filesystem;

    public function __construct(IO $io, TemplateRenderer $templates, Filesystem $filesystem = null)
    {
        $this->io         = $io;
        $this->templates  = $templates;
        $this->filesystem = $filesystem ?: new Filesystem;
    }

    public function generate(ResourceInterface $resource, array $data = array())
    {
        $filepath = $resource->getSrcFilename();
        if ($this->filesystem->pathExists($filepath)) {
            $message = sprintf('File "%s" already exists. Overwrite?', basename($filepath));
            if (!$this->io->askConfirmation($message, false)) {
                return;
            }

            $this->io->writeln();
        }

        $path = dirname($filepath);
        if (!$this->filesystem->isDirectory($path)) {
            $this->filesystem->makeDirectory($path);
        }

        $content = $this->renderTemplate($resource, $filepath);

        $this->filesystem->putFileContents($filepath, $content);
        $this->io->writeln($this->getGenerationOutputMessage($resource));
    }

    public function getPriority()
    {
        return 0;
    }

    protected function renderTemplate(ResourceInterface $resource, $filepath)
    {
        $values = array(
            '%filepath%'        => $filepath,
            '%name%'            => $resource->getName(),
            '%namespace%'       => $resource->getSrcNamespace(),
            '%namespace_block%' => '' !== $resource->getSrcNamespace()
                                ?  sprintf("\n\nnamespace %s;", $resource->getSrcNamespace())
                                : '',
        );

        if (!$content = $this->getContent($values)) {
            $content = $this->templates->renderString(
                $this->getTemplate(), $values
            );
        }

        return $content;
    }

    /**
     * Get template for rendering
     *
     * @return string
     */
    abstract protected function getTemplate();

    /**
     * Get message to display after rendering template
     *
     * @param PhpSpec\Locator\ResourceInterface $resource
     *
     * @return string
     */
    abstract protected function getGenerationOutputMessage(ResourceInterface $resource);

    /**
     * Get content for template
     *
     * @param array $values values to generate content
     *
     * @return string
     */
    abstract protected function getContent(array $values);
}
