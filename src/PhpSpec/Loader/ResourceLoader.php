<?php

namespace PhpSpec\Loader;

use PhpSpec\Locator\ResourceManager;

use ReflectionClass;
use ReflectionMethod;

class ResourceLoader
{
    private $manager;

    public function __construct(ResourceManager $manager)
    {
        $this->manager = $manager;
    }

    public function load($locator, $line = null)
    {
        $suite = new Suite;
        foreach ($this->manager->locateResources($locator) as $resource) {
            if (!class_exists($resource->getSpecClassname()) && is_file($resource->getSpecFilename())) {
                require_once $resource->getSpecFilename();
            }
            if (!class_exists($resource->getSpecClassname())) {
                continue;
            }

            $reflection = new ReflectionClass($resource->getSpecClassname());

            if ($reflection->isAbstract()) {
                continue;
            }
            if (!$reflection->implementsInterface('PhpSpec\SpecificationInterface')) {
                continue;
            }

            $spec = new Node\SpecificationNode($resource->getSrcClassname(), $reflection, $resource);

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $specAnnotation = null;

                $methodDocComment = $method->getDocComment();

                if (!empty($methodDocComment)) {
                    foreach (preg_split('/\R[^*]*/', $methodDocComment) as $docLine) {
                        if (
                            0 !== strpos($docLine, '* @it ')
                            && 0 !== strpos($docLine, '* @its ')
                        ) {
                            continue;
                        }

                        $specAnnotation = str_replace('* @', '', $docLine);
                        break;
                    }
                }



                if (null === $specAnnotation) {
                    $specAnnotation = $method->getName();

                    if (
                        0 !== strpos($specAnnotation, 'it_') 
                        && 0 !== strpos($specAnnotation, 'its_')
                    ) {
                        continue;
                    }

                    $specAnnotation = str_replace('_', ' ', $specAnnotation);
                }

                if (null !== $line && !$this->lineIsInsideMethod($line, $method)) {
                    continue;
                }

                $example = new Node\ExampleNode($specAnnotation, $method);

                if ($this->methodIsEmpty($method)) {
                    $example->markAsPending();
                }

                $spec->addExample($example);
            }

            $suite->addSpecification($spec);
        }

        return $suite;
    }

    private function lineIsInsideMethod($line, ReflectionMethod $method)
    {
        $line = intval($line);

        return $line >= $method->getStartLine() && $line <= $method->getEndLine();
    }

    private function methodIsEmpty(ReflectionMethod $method)
    {
        $filename = $method->getFileName();
        $lines    = explode("\n", file_get_contents($filename));
        $function = trim(implode("\n",
            array_slice($lines,
                $method->getStartLine() - 1,
                $method->getEndLine() - $method->getStartLine()
            )
        ));

        $function = trim(preg_replace(
            array('|^[^}]*{|', '|}$|', '|//[^\n]*|s', '|/\*.*\*/|s'), '', $function
        ));

        return '' === $function;
    }
}
