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

use PhpSpec\Locator\Resource;

/**
 * The Interface Generator is responsible for generating the interface from a resource
 * in the appropriate folder using the template provided
 */
final class InterfaceGenerator extends PromptingGenerator
{
    /**
     * @param Resource $resource
     * @param string            $generation
     * @param array             $data
     *
     * @return bool
     */
    public function supports(Resource $resource, $generation, array $data)
    {
        return 'interface' === $generation;
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return 0;
    }

    /**
     * @param Resource $resource
     * @param string            $filepath
     *
     * @return string
     */
    protected function renderTemplate(Resource $resource, $filepath)
    {
        $values = array(
            '%filepath%'        => $filepath,
            '%name%'            => $resource->getName(),
            '%namespace%'       => $resource->getSrcNamespace(),
            '%namespace_block%' => '' !== $resource->getSrcNamespace()
                ?  sprintf("\n\nnamespace %s;", $resource->getSrcNamespace())
                : '',
        );

        if (!$content = $this->getTemplateRenderer()->render('interface', $values)) {
            $content = $this->getTemplateRenderer()->renderString(
                $this->getTemplate(), $values
            );
        }

        return $content;
    }

    /**
     * @return string
     */
    protected function getTemplate()
    {
        return file_get_contents(__DIR__.'/templates/interface.template');
    }

    /**
     * @param Resource $resource
     *
     * @return string
     */
    protected function getFilePath(Resource $resource)
    {
        return $resource->getSrcFilename();
    }

    /**
     * @param Resource $resource
     * @param string            $filepath
     *
     * @return string
     */
    protected function getGeneratedMessage(Resource $resource, $filepath)
    {
        return sprintf(
            "<info>Interface <value>%s</value> created in <value>%s</value>.</info>\n",
            $resource->getSrcClassname(), $filepath
        );
    }
}
