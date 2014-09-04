<?php

namespace PhpSpec\CodeGenerator\Generator;

use PhpSpec\CodeGenerator\TemplateRenderer;
use PhpSpec\Console\IO;
use PhpSpec\Locator\ResourceInterface;
use PhpSpec\Util\Filesystem;
use ReflectionMethod;

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
        $filepath   = $resource->getSrcFilename();
        $methodName = $data['name'];
        $arguments  = $data['arguments'];

        $argString = count($arguments)
            ? '$argument'.implode(', $argument',  range(1, count($arguments)))
            : ''
        ;

        $commonValues = $this->getCommonTemplateValues($methodName, $argString);

        $content = $this->getTemplateContent($resource->getName(), $commonValues);

        if (method_exists($resource->getSrcClassname(), '__construct')) {
            $content = $this->getExistingConstructorContent(
                $resource->getSrcClassname(),
                $resource->getName(),
                $arguments,
                $commonValues
            );
        }

        $code = $this->filesystem->getFileContents($filepath);
        $code = preg_replace('/}[ \n]*$/', rtrim($content) ."\n}\n", trim($code));
        $this->filesystem->putFileContents($filepath, $code);

        $this->io->writeln(sprintf(
            "\n<info>Method <value>%s::%s()</value> has been created.</info>",
            $resource->getSrcClassname(), $methodName
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

    /**
     * @return string
     */
    protected function getExceptionTemplate()
    {
        return file_get_contents(__DIR__ . '/templates/named_constructor_exception.template');
    }

    /**
     * @param string $className
     * @param array  $templateValues
     * @return string
     */
    private function getTemplateContent($className, array $templateValues)
    {
        $templateValues = $this->getTemplateValues($className, $templateValues);

        if (!$content = $this->templates->render('named_constructor', $templateValues)) {
            $content = $this->templates->renderString(
                $this->getTemplate(), $templateValues
            );
        }
        return $content;
    }

    /**
     * @param string $class
     * @param string $className
     * @param array  $arguments
     * @param array  $values
     * @return string
     */
    private function getExistingConstructorContent($class, $className, array $arguments, array $values)
    {
        $numberOfConstructorArguments = $this->getNumberOfConstructorArguments($class);

        if ($numberOfConstructorArguments != count($arguments)) {
            return $this->getExceptionTemplateContent($values);
        }

        $values['%constructorArguments%'] = $values['%arguments%'];
        return $this->getTemplateContent($className, $values);
    }

    /**
     * @param string $class
     * @return int
     */
    private function getNumberOfConstructorArguments($class)
    {
        $constructorArguments = 0;

        $constructor = new ReflectionMethod($class, '__construct');
        $params = $constructor->getParameters();

        foreach ($params as $param) {
            if (!$param->isOptional()) {
                $constructorArguments++;
            }
        }

        return $constructorArguments;
    }

    /**
     * @param array $values
     * @return string
     */
    private function getExceptionTemplateContent(array $values)
    {
        if (!$content = $this->templates->render('named_constructor_exception', $values)) {
            $content = $this->templates->renderString(
                $this->getExceptionTemplate(), $values
            );
        }
        return $content;
    }

    /**
     * @param string $methodName
     * @param string $argString
     * @return array
     */
    private function getCommonTemplateValues($methodName, $argString)
    {
        return array(
            '%methodName%'           => $methodName,
            '%arguments%'            => $argString,
            '%constructorArguments%' => ''
        );
    }

    /**
     * @param string $class
     * @param array  $values
     * @return array
     */
    private function getTemplateValues($class, array $values)
    {
        $values['%returnVar%'] = '$' . lcfirst($class);
        $values['%className%'] = $class;
        return $values;
    }
}
