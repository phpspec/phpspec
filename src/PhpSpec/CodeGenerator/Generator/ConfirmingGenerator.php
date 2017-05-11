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

use PhpSpec\Console\IO;
use PhpSpec\Locator\ResourceInterface;

final class ConfirmingGenerator implements GeneratorInterface
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
     * @var GeneratorInterface
     */
    private $generator;

    /**
     * @param IO                 $io
     * @param string             $message
     * @param GeneratorInterface $generator
     */
    public function __construct(IO $io, $message, GeneratorInterface $generator)
    {
        $this->io = $io;
        $this->message = $message;
        $this->generator = $generator;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(ResourceInterface $resource, $generation, array $data)
    {
        return $this->generator->supports($resource, $generation, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function generate(ResourceInterface $resource, array $data)
    {
        if ($this->io->askConfirmation($this->composeMessage($resource))) {
            $this->generator->generate($resource, $data);
        }
    }

    /**
     * @param ResourceInterface $resource
     *
     * @return string
     */
    private function composeMessage(ResourceInterface $resource)
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
