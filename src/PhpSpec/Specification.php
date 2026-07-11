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

use PhpSpec\Coverage\CoverageRegistry;
use PhpSpec\EventDispatcher\DispatcherRegistry;
use PhpSpec\EventDispatcher\Event\SpecificationFinished;
use PhpSpec\EventDispatcher\Event\SpecificationStarted;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Specification\ExampleRegistry;
use PhpSpec\Specification\Rebindable;
use PhpSpec\Specification\SpecBlock;
use PhpSpec\Specification\SpecFileCache;
use PhpSpec\Specification\Subject;

/**
 * @internal
 * Represents a single .spec.php file. Loads the file, executes its describe/context
 * blocks, and collects results into a SpecificationResult.
 */
class Specification implements ExampleRegistry, SpecBlock
{
    /** @var array<SpecBlock> top-level describe/context blocks from this spec file */
    private array $specBlocks = [];

    /**
     * @param string $path filesystem path to the spec file
     * @param string $specSuffix the spec file suffix to strip for title derivation
     */
    public function __construct(
        private string $path,
        private string $specSuffix = '.spec.php',
    ) {}

    /**
     * Loads and executes the spec file, running all registered spec blocks.
     *
     * @return Results aggregated SpecificationResult
     */
    public function run(): Results
    {
        DispatcherRegistry::dispatcher()->dispatch(new SpecificationStarted($this->path), SpecificationStarted::NAME);
        CoverageRegistry::collector()?->beginSpec($this->path);
        FilterRegistry::current()?->beginSpec($this->path);
        LineTargetRegistry::beginSpec($this->path);

        $subject = $this->loadSubject();

        $blockResults = [];

        foreach ($this->targetedSpecBlocks() as $specBlock) {
            if ($specBlock instanceof Specification\Context) {
                $specBlock->setWorld($subject);
            }

            $blockResults[] = $specBlock->run();
        }

        $result = new SpecificationResult($this->getTitle(), $blockResults);
        DispatcherRegistry::dispatcher()->dispatch(new SpecificationFinished($this->getTitle()), SpecificationFinished::NAME);
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
     * Returns the top-level blocks to run, honouring an active line target:
     * only blocks whose closure spans the targeted line run, and none when
     * the line falls outside every block.
     *
     * @return array<SpecBlock> the blocks addressed by the line target, or all blocks
     */
    private function targetedSpecBlocks(): array
    {
        $line = LineTargetRegistry::currentTarget();

        if ($line === null) {
            return $this->specBlocks;
        }

        return array_values(array_filter(
            $this->specBlocks,
            fn(SpecBlock $block) => $block instanceof Specification\Context && $block->containsLine($line),
        ));
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
     * Builds a fresh world for this run and registers the spec file's top-level
     * blocks against it, rebound to that world. The file is parsed (require'd)
     * at most once per process by SpecFileCache; every run works from fresh
     * copies of the cached templates so repeated runs never re-execute the file.
     *
     * @return Subject the world shared by this run's examples
     */
    private function loadSubject(): Subject
    {
        $world = new Subject();

        foreach (SpecFileCache::templates($this->path) as $template) {
            $this->addSpecBlock($template instanceof Rebindable ? $template->withWorld($world) : $template);
        }

        return $world;
    }
}
