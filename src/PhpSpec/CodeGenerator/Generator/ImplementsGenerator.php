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

use PhpSpec\CodeGenerator\Writer\CodeWriter;
use PhpSpec\Locator\Resource;
use PhpSpec\Util\Filesystem;

final class ImplementsGenerator implements Generator
{
    /**
     * @var Filesystem
     */
    private $fs;

    /**
     * @var CodeWriter
     */
    private $codeWriter;

    /**
     * @param Filesystem $fs
     * @param CodeWriter $codeWriter
     */
    public function __construct(Filesystem $fs, CodeWriter $codeWriter)
    {
        $this->fs = $fs;
        $this->codeWriter = $codeWriter;
    }

    /**
     * @param Resource $resource
     * @param string   $generation
     * @param array    $data
     *
     * @return bool
     */
    public function supports(Resource $resource, string $generation, array $data) : bool
    {
        return 'implements' === $generation;
    }

    /**
     * @param Resource $resource
     * @param array    $data
     */
    public function generate(Resource $resource, array $data)
    {
        $filepath = $resource->getSrcFilename();
        $interface = $data['interface'];

        $code = $this->fs->getFileContents($filepath);

        $this->fs->putFileContents($filepath, $this->codeWriter->insertImplementsInClass($code, $interface));
    }

    /**
     * @return int
     */
    public function getPriority() : int
    {
        return 0;
    }
}
