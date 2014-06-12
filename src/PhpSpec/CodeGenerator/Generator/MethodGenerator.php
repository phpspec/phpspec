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

        $argsArray  = array();
        $namespaces = array();

        $total = count($arguments);
        for ($i = 0; $i < $total; $i++) {
            $argument     = $arguments[$i];

            $argumentType = gettype($argument);
            if ($argumentType === 'object') {
                $className   = $this->getClassName($argument);
                $argsArray[] = $className . ' $' . lcfirst($className) . ($i + 1);

                if ($namespace = $this->getNamespace($argument)) {
                    $namespaces[] = $namespace . '\\' . $className;
                }
            } else {
                $argsArray[] = '$' . $argumentType . ($i + 1);
            }
        }

        $argString = implode(', ', $argsArray);

        $values = array('%name%' => $name, '%arguments%' => $argString);
        if (!$content = $this->templates->render('method', $values)) {
            $content = $this->templates->renderString(
                $this->getTemplate(), $values
            );
        }

        $code = $this->filesystem->getFileContents($filepath);

        $matches = array();
        if (preg_match('/^\s*namespace\s+(?<namespace>[^;]+);\s*$/m', $code, $matches)) {
            $fileNamespace = $matches['namespace'];

            $matches = array();
            preg_match_all('/^\s*use\s+(?<namespace>[^;]+);\s*$/m', $code, $matches);
            $knownNamespaces = array_unique($matches['namespace']);

            $missingNamespaces = array();
            $namespaces = array_unique($namespaces);
            foreach ($namespaces as $namespace) {
                if (strpos($namespace, $fileNamespace . '\\') !== 0) {
                    if (!in_array($namespace, $knownNamespaces)) {
                        $missingNamespaces[] = $namespace;
                    }
                }
            }

            if (count($missingNamespaces) > 0) {
                $namespacesString = "\n" . implode("\n", array_map(function ($namespace) {
                    return 'use ' . $namespace . ';';
                }, $missingNamespaces));

                $code = preg_replace('/\nclass/', $namespacesString."\n\nclass", trim($code));
            }
        }

        $code = preg_replace('/}[ \n]*$/', rtrim($content) ."\n}\n", trim($code));
        $this->filesystem->putFileContents($filepath, $code);

        $this->io->writeln(sprintf(
            "\n<info>Method <value>%s::%s()</value> has been created.</info>",
            $resource->getSrcClassname(), $name
        ), 2);
    }

    /**
     * @param  mixed $object
     * @return string
     */
    private function getClassName($object)
    {
        $spaces = array_slice(explode('\\', get_class($object)), 1, -1);
        return array_slice($spaces, -1)[0];
    }

    /**
     * @param  mixed       $object
     * @return string|null
     */
    private function getNamespace($object)
    {
        $spaces = array_slice(explode('\\', get_class($object)), 1, -1);
        if (count($spaces) > 1) {
            return implode('\\', array_slice($spaces, 0, -1));
        } else {
            return null;
        }
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
        return file_get_contents(__FILE__, null, null, __COMPILER_HALT_OFFSET__);
    }
}
__halt_compiler();
    public function %name%(%arguments%)
    {
        // TODO: write logic here
    }
