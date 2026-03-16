<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 * (c) Ciaran McNulty <ciaran@ciaranmcnulty.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec;

use PhpSpec\EventDispatcher\Dispatcher;
use PhpSpec\EventDispatcher\DispatcherRegistry;
use PhpSpec\EventDispatcher\Event\SpecificationFinished;
use PhpSpec\EventDispatcher\Event\SpecificationStarted;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Specification\ExampleRegistry;
use PhpSpec\Specification\SpecBlock;
use PhpSpec\Specification\Subject;

/**
 * Represents a single .spec.php file. Loads the file, executes its describe/context
 * blocks, and collects results into a SpecificationResult.
 */
class Specification implements ExampleRegistry, SpecBlock
{
    /** @var array<SpecBlock> top-level describe/context blocks from this spec file */
    private array $specBlocks = [];

    private readonly Dispatcher $dispatcher;

    /**
     * @param string $path filesystem path to the spec file
     * @param string $specSuffix the spec file suffix to strip for title derivation
     * @param Dispatcher|null $dispatcher event dispatcher instance
     */
    public function __construct(
        private string $path,
        private string $specSuffix = '.spec.php',
        ?Dispatcher $dispatcher = null,
    ) {
        $this->dispatcher = $dispatcher ?? DispatcherRegistry::get();
    }

    /**
     * Loads and executes the spec file, running all registered spec blocks.
     *
     * @return Results aggregated SpecificationResult
     */
    public function run(): Results
    {
        $this->dispatcher->dispatch(new SpecificationStarted($this->path), SpecificationStarted::NAME);

        // loads the specification file from the Subject constructor
        // so $this in the examples refers to Subject
        $this->dispatcher->pushScope($this);
        $subject = $this->loadSubject();
        $this->dispatcher->popScope();

        $blockResults = [];

        foreach ($this->getSpecBlocks() as $specBlock) {
            if ($specBlock instanceof Specification\Context) {
                $specBlock->setWorld($subject);
            }

            $blockResults[] = $specBlock->run();
        }

        $result = new SpecificationResult($this->getTitle(), $blockResults);
        $this->dispatcher->dispatch(new SpecificationFinished($this->getTitle()), SpecificationFinished::NAME);
        return $result;
    }

    /**
     * Returns the filesystem path to the spec file.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Registers a top-level spec block (describe/context) discovered during file loading.
     *
     * @param SpecBlock $block context or example to add
     * @return void
     */
    public function addSpecBlock(SpecBlock $block): void
    {
        $this->specBlocks[] = $block;
    }

    /**
     * Returns all registered spec blocks.
     *
     * @return array<SpecBlock>
     */
    public function getSpecBlocks(): array
    {
        return $this->specBlocks;
    }

    /**
     * Clears all registered spec blocks.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->specBlocks = [];
    }

    /**
     * Derives a human-readable title from the spec filename (strips .spec.php suffix).
     */
    private function getTitle(): string
    {
        return substr(basename($this->path), 0, -strlen($this->specSuffix));
    }

    /**
     * Creates a Subject that loads the spec file, making $this available inside closures.
     */
    private function loadSubject(): Subject
    {
        return new Subject($this->path);
    }
}
