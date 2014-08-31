<?php

namespace PhpSpec\CodeGenerator\Generator;

use PhpSpec\CodeGenerator\TemplateRenderer;
use PhpSpec\Console\IO;
use PhpSpec\Locator\ResourceInterface;
use PhpSpec\Util\Filesystem;

class NamedConstructorGenerator implements GeneratorInterface
{
    /**
     * @var \PhpSpec\Console\IO
     */
    private $io;

    /**
     * @var \PhpSpec\CodeGenerator\TemplateRenderer
     */
    private $templates;

    /**
     * @var \PhpSpec\Util\Filesystem
     */
    private $filesystem;

    /**
     * @param IO               $io
     * @param TemplateRenderer $templates
     * @param Filesystem       $filesystem
     */
    public function __construct(IO $io, TemplateRenderer $templates, Filesystem $filesystem = null)
    {
        $this->io         = $io;
        $this->templates  = $templates;
        $this->filesystem = $filesystem ?: new Filesystem;
    }

    /**
     * @param ResourceInterface $resource
     * @param string            $generation
     * @param array             $data
     *
     * @return bool
     */
    public function supports(ResourceInterface $resource, $generation, array $data)
    {
        return 'named_constructor' === $generation;
    }

    /**
     * @param ResourceInterface $resource
     * @param array             $data
     */
    public function generate(ResourceInterface $resource, array $data = array())
    {
        $filepath  = $resource->getSrcFilename();
        $name      = $data['name'];
        $arguments = $data['arguments'];

        $argString = count($arguments)
            ? '$argument'.implode(', $argument',  range(1, count($arguments)))
            : ''
        ;

        $values = array(
            '%name%' => $name,
            '%arguments%' => $argString,
            '%returnVar%' => '$' . lcfirst($resource->getName()),
            '%className%' => $resource->getName()
        );
        if (!$content = $this->templates->render('named_constructor', $values)) {
            $content = $this->templates->renderString(
                $this->getTemplate(), $values
            );
        }

        $code = $this->filesystem->getFileContents($filepath);
        $code = preg_replace('/}[ \n]*$/', rtrim($content) ."\n}\n", trim($code));
        $this->filesystem->putFileContents($filepath, $code);

        $this->io->writeln(sprintf(
            "\n<info>Method <value>%s::%s()</value> has been created.</info>",
            $resource->getSrcClassname(), $name
        ), 2);
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return 0;
    }

    /**
     * @return string
     */
    protected function getTemplate()
    {
        return file_get_contents(__DIR__ . '/templates/named_constructor.template');
    }
}
