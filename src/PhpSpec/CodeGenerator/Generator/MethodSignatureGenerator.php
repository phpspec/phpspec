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
use PhpSpec\Util\Filesystem;
use PhpSpec\Locator\Resource;

/**
 * Generates interface method signatures from a resource
 */
final class MethodSignatureGenerator implements Generator
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
     * @param ConsoleIO               $io
     * @param TemplateRenderer $templates
     * @param Filesystem       $filesystem
     */
    public function __construct(ConsoleIO $io, TemplateRenderer $templates, Filesystem $filesystem)
    {
        $this->io         = $io;
        $this->templates  = $templates;
        $this->filesystem = $filesystem;
    }

    /**
     * @param Resource $resource
     * @param string            $generation
     * @param array             $data
     *
     * @return bool
     */
    public function supports(Resource $resource, $generation, array $data)
    {
        return 'method-signature' === $generation;
    }

    /**
     * @param Resource $resource
     * @param array             $data
     */
    public function generate(Resource $resource, array $data = array())
    {
        $filepath  = $resource->getSrcFilename();
        $name      = $data['name'];
        $arguments = $data['arguments'];

        $argString = $this->buildArgumentString($arguments);

        $values = array('%name%' => $name, '%arguments%' => $argString);
        if (!$content = $this->templates->render('interface-method-signature', $values)) {
            $content = $this->templates->renderString(
                $this->getTemplate(), $values
            );
        }

        $this->insertMethodSignature($filepath, $content);

        $this->io->writeln(sprintf(
            "<info>Method signature <value>%s::%s()</value> has been created.</info>\n",
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
        return file_get_contents(__DIR__.'/templates/interface_method_signature.template');
    }

    /**
     * @param string $filepath
     * @param string $content
     */
    private function insertMethodSignature($filepath, $content)
    {
        $code = $this->filesystem->getFileContents($filepath);
        $code = preg_replace('/}[ \n]*$/', rtrim($content) . "\n}\n", trim($code));
        $this->filesystem->putFileContents($filepath, $code);
    }

    /**
     * @param array $arguments
     * @return string
     */
    private function buildArgumentString($arguments)
    {
        $argString = count($arguments)
            ? '$argument' . implode(', $argument', range(1, count($arguments)))
            : '';
        return $argString;
    }
}
