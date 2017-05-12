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

use PhpSpec\IO\IO;
use PhpSpec\Locator\Resource;

final class ConfirmingGenerator implements Generator
{
    /**
     * @var IO
     */
    private $io;

    /**
     * @var string
     */
    private $message;

    /**
     * @var Generator
     */
    private $generator;

    /**
     * @param IO                 $io
     * @param string             $message
     * @param Generator $generator
     */
    public function __construct(IO $io, $message, Generator $generator)
    {
        $this->io = $io;
        $this->message = $message;
        $this->generator = $generator;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Resource $resource, $generation, array $data)
    {
        return $this->generator->supports($resource, $generation, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function generate(Resource $resource, array $data)
    {
        if ($this->io->askConfirmation($this->composeMessage($resource))) {
            $this->generator->generate($resource, $data);
        }
    }

    /**
     * @param Resource $resource
     *
     * @return string
     */
    private function composeMessage(Resource $resource)
    {
        return str_replace('{CLASSNAME}', $resource->getSrcClassname(), $this->message);
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        return $this->generator->getPriority();
    }
}
