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
use PhpSpec\Locator\ResourceInterface;
use PhpSpec\CodeGenerator\Writer\CodeWriter;
use PhpSpec\Util\Filesystem;
use PhpSpec\CodeGenerator\Writer\TokenizedCodeWriter;

final class PrivateConstructorGenerator implements GeneratorInterface
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
     * @param IO               $io
     * @param TemplateRenderer $templates
     * @param Filesystem       $filesystem
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
     * @param string $generation
     * @param array $data
     *
     * @return bool
     */
    public function supports(ResourceInterface $resource, $generation, array $data)
    {
        return 'private-constructor' === $generation;
    }

    /**
     * @param ResourceInterface $resource
     * @param array $data
     */
    public function generate(ResourceInterface $resource, array $data)
    {
        $filepath  = $resource->getSrcFilename();

        if (!$content = $this->templates->render('private-constructor', array())) {
            $content = $this->templates->renderString(
                $this->getTemplate(),
                array()
            );
        }

        $code = $this->filesystem->getFileContents($filepath);
        $code = $this->codeWriter->insertMethodFirstInClass($code, $content);
        $this->filesystem->putFileContents($filepath, $code);

        $this->io->writeln("<info>Private constructor has been created.</info>\n", 2);
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
        return file_get_contents(__DIR__.'/templates/private-constructor.template');
    }
}
