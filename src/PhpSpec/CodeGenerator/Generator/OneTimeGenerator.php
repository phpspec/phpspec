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

use PhpSpec\Locator\ResourceInterface;

final class OneTimeGenerator implements GeneratorInterface
{
    /**
     * @var GeneratorInterface
     */
    private $generator;

    /**
     * @var array
     */
    private $alreadyGenerated = array();

    /**
     * @param GeneratorInterface $generator
     */
    public function __construct(GeneratorInterface $generator)
    {
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
        $classname = $resource->getSrcClassname();
        if (in_array($classname, $this->alreadyGenerated)) {
            return;
        }

        $this->generator->generate($resource, $data);
        $this->alreadyGenerated[] = $classname;
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        return $this->generator->getPriority();
    }
}
