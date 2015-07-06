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

use PhpSpec\CodeGenerator\TemplateRenderer;
use PhpSpec\Console\IO;
use PhpSpec\Exception\Generator\NamedMethodNotFoundException;
use PhpSpec\Locator\ResourceInterface;
use PhpSpec\CodeGenerator\Writer\CodeWriter;
use PhpSpec\Util\Filesystem;
use PhpSpec\CodeGenerator\Writer\TokenizedCodeWriter;

class NamedConstructorGenerator implements GeneratorInterface
{
    /**
     * @var IO
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
     * @var CodeWriter
     */
    private $codeWriter;

    /**
     * @param IO $io
     * @param TemplateRenderer $templates
     * @param Filesystem $filesystem
     * @param CodeWriter $codeWriter
     */
    public function __construct(IO $io, TemplateRenderer $templates, Filesystem $filesystem = null, CodeWriter $codeWriter = null)
    {
        $this->io         = $io;
        $this->templates  = $templates;
        $this->filesystem = $filesystem ?: new Filesystem();
        $this->codeWriter = $codeWriter ?: new TokenizedCodeWriter();
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
        $filepath   = $resource->getSrcFilename();
        $methodName = $data['name'];
        $arguments  = $data['arguments'];

        $content = $this->getContent($resource, $methodName, $arguments);


        $code = $this->appendMethodToCode(
            $this->filesystem->getFileContents($filepath),
            $content
        );
        $this->filesystem->putFileContents($filepath, $code);

        $this->io->writeln(sprintf(
            "<info>Method <value>%s::%s()</value> has been created.</info>\n",
            $resource->getSrcClassname(),
            $methodName
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
     * @param  ResourceInterface $resource
     * @param  string            $methodName
     * @param  array             $arguments
     * @return string
     */
    private function getContent(ResourceInterface $resource, $methodName, $arguments)
    {
        $className = $resource->getName();
        $class = $resource->getSrcClassname();

        $template = new CreateObjectTemplate($this->templates, $methodName, $arguments, $className);

        if (method_exists($class, '__construct')) {
            $template = new ExistingConstructorTemplate(
                $this->templates,
                $methodName,
                $arguments,
                $className,
                $class
            );
        }

        return $template->getContent();
    }

    /**
     * @param string $code
     * @param string $method
     * @return string
     */
    private function appendMethodToCode($code, $method)
    {
        try {
            return $this->codeWriter->insertAfterMethod($code, '__construct', $method);
        } catch (NamedMethodNotFoundException $e) {
            return $this->codeWriter->insertMethodFirstInClass($code, $method);
        }
    }
}
