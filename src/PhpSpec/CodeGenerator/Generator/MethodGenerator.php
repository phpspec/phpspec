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

use PhpSpec\Console\IO;
use PhpSpec\CodeGenerator\TemplateRenderer;
use PhpSpec\Util\Filesystem;
use PhpSpec\Locator\ResourceInterface;

/**
 * Generates class methods from a resource
 */
class MethodGenerator implements GeneratorInterface
{
    const METHOD_PLACEMENT = '/}[ \n]*$/';
    const CONSTRUCTOR_PLACEMENT = '/\n(?=\s*(private|protected|public)?\s?function)/';

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
        $this->filesystem = $filesystem ?: new Filesystem();
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
        return 'method' === $generation;
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
            ? '$argument'.implode(', $argument', range(1, count($arguments)))
            : ''
        ;

        $values = array('%name%' => $name, '%arguments%' => $argString);
        if (!$content = $this->templates->render('method', $values)) {
            $content = $this->templates->renderString(
                $this->getTemplate(),
                $values
            );
        }

        $code = $this->filesystem->getFileContents($filepath);
        $this->filesystem->putFileContents($filepath, $this->getUpdatedCode($name, $content, $code));

        $this->io->writeln(sprintf(
            "<info>Method <value>%s::%s()</value> has been created.</info>\n",
            $resource->getSrcClassname(),
            $name
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
        return file_get_contents(__DIR__.'/templates/method.template');
    }

    /**
     * @param string $methodName
     * @param string $snippetToInsert
     * @param string $code
     * @return string
     */
    private function getUpdatedCode($methodName, $snippetToInsert, $code)
    {
        if ('__construct' === $methodName && $this->codeContainsAFunction($code)) {
            return preg_replace(self::CONSTRUCTOR_PLACEMENT, rtrim($snippetToInsert)."\n\n", $code, 1);
        }
        return preg_replace(self::METHOD_PLACEMENT, rtrim($snippetToInsert)."\n}\n", trim($code));
    }

    /**
     * @param $code
     * @return bool
     */
    private function codeContainsAFunction($code)
    {
        return false !== strpos($code, 'function');
    }
}
