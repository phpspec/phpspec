<?php

namespace PhpSpec\CodeGenerator\Generator;

use PhpSpec\Locator\ResourceInterface;

class InterfaceGenerator extends FileGenerator
{
    public function supports(ResourceInterface $resource, $generation, array $data)
    {
        return 'interface' === $generation;
    }

    protected function getGenerationOutputMessage(ResourceInterface $resource)
    {
        return sprintf(
            "<info>Interface <value>%s</value> created in <value>%s</value>.</info>\n",
            $resource->getSrcClassname(), $resource->getSrcFilename()
        );
    }

    protected function getContent(array $values)
    {
        return $this->templates->render('interface', $values);
    }

    protected function getTemplate()
    {
        return file_get_contents(__FILE__, null, null, __COMPILER_HALT_OFFSET__);
    }
}
__halt_compiler();<?php%namespace_block%

interface %name%
{
}

