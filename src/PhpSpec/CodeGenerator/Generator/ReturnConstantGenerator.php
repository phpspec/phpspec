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
use PhpSpec\Console\ConsoleIO;
use PhpSpec\Locator\Resource;
use PhpSpec\Util\Filesystem;

final class ReturnConstantGenerator implements Generator
{
    /**
     * @var TemplateRenderer
     */
    private $templates;
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @param TemplateRenderer $templates
     * @param Filesystem       $filesystem
     */
    public function __construct(TemplateRenderer $templates, Filesystem $filesystem)
    {
        $this->templates = $templates;
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
        return 'returnConstant' == $generation;
    }

    /**
     * @param Resource $resource
     * @param array    $data
     *
     * @return string
     */
    public function generate(Resource $resource, array $data)
    {
        $method = $data['method'];
        $expected = $data['expected'];

        $code = $this->filesystem->getFileContents($resource->getSrcFilename());

        $values = array('%constant%' => var_export($expected, true));
        if (!$content = $this->templates->render('method', $values)) {
            $content = $this->templates->renderString(
                $this->getTemplate(),
                $values
            );
        }

        $pattern = '/'.'(function\s+'.preg_quote($method, '/').'\s*\([^\)]*\))\s+{[^}]*?}/';
        $replacement = '$1'.$content;

        $modifiedCode = preg_replace($pattern, $replacement, $code);

        $this->filesystem->putFileContents($resource->getSrcFilename(), $modifiedCode);

        return sprintf(
            "<info>Method <value>%s::%s()</value> has been modified.</info>\n",
            $resource->getSrcClassname(),
            $method
        );
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
        return file_get_contents(__DIR__.'/templates/returnconstant.template');
    }
}
