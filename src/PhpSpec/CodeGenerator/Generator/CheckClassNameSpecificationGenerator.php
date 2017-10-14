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


use PhpSpec\Console\ConsoleIO;
use PhpSpec\Util\NameChecker;
use PhpSpec\Locator\Resource;

final class CheckClassNameSpecificationGenerator implements Generator
{

    private $classNameChecker;
    private $io;
    private $originalGenerator;

    public function __construct(NameChecker $classNameChecker, ConsoleIO $io, Generator $originalGenerator)
    {
        $this->classNameChecker = $classNameChecker;
        $this->io = $io;
        $this->originalGenerator = $originalGenerator;
    }

    public function supports(Resource $resource, string $generation, array $data): bool
    {
        return $this->originalGenerator->supports($resource, $generation, $data);
    }

    public function generate(Resource $resource, array $data)
    {
        $className = $resource->getSrcClassname();

        if (!$this->classNameChecker->isNameValid($className)) {
            //todo add used keyword to message too
            $this->writeInvalidClassNameError($className);
            return;
        }

        $this->originalGenerator->generate($resource, $data);
    }

    public function getPriority(): int
    {
        return $this->originalGenerator->getPriority();
    }

    public function writeInvalidClassNameError($className): void
    {
        $error = sprintf('I cannot generate class \'%s\' for you cause it contains reserved keyword', $className);
        $this->io->writeBrokenCodeBlock($error, 2);
    }

}
