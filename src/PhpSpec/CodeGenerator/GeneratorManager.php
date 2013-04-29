<?php

namespace PhpSpec\CodeGenerator;

use PhpSpec\Locator\ResourceInterface;

use InvalidArgumentException;

class GeneratorManager
{
    private $generators;

    public function registerGenerator(Generator\GeneratorInterface $generator)
    {
        $this->generators[] = $generator;
        @usort($this->generators, function($generator1, $generator2) {
            return $generator2->getPriority() - $generator1->getPriority();
        });
    }

    public function generate(ResourceInterface $resource, $name, array $data = array())
    {
        foreach ($this->generators as $generator) {
            if ($generator->supports($resource, $name, $data)) {
                return $generator->generate($resource, $data);
            }
        }

        throw new InvalidArgumentException(sprintf(
            '"%s" code generator is not registered.', $name
        ));
    }
}
