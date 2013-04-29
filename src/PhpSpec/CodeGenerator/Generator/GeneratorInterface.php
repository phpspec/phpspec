<?php

namespace PhpSpec\CodeGenerator\Generator;

use PhpSpec\Locator\ResourceInterface;

interface GeneratorInterface
{
    public function supports(ResourceInterface $resource, $generation, array $data);
    public function generate(ResourceInterface $resource, array $data);
    public function getPriority();
}
