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

namespace PhpSpec\StoryBDD;

use PhpSpec\CodeGeneration\SurroundingCode;

/**
 * Wraps a throwable that occurred during step execution, preserving the original
 * exception's file, line, and class type for error reporting.
 */
final class StepError extends \Exception
{
    /** @var string the class name of the original throwable */
    private string $type;

    /**
     * Wraps an original throwable, preserving its file and line for error reporting.
     *
     * @param mixed $message the error message
     * @param \Throwable $original the original throwable that caused the step failure
     */
    public function __construct(protected $message, protected \Throwable $original)
    {
        $this->line = $original->getLine();
        $this->file = $original->getFile();
        $this->type = get_class($original);
    }

    /**
     * Returns source code lines surrounding the error location.
     *
     * @param int $before number of lines to include before the error line
     * @param int $after number of lines to include after the error line
     * @return array the surrounding code lines
     */
    public function getSurroundingCode(int $before = 3, int $after = 3): array
    {
        $surroundingCode = new SurroundingCode($this->original->getFile(), $this->original->getLine(), $before, $after);
        return $surroundingCode->toArray();
    }

    /**
     * Returns the class name of the original throwable (e.g. "RuntimeException").
     *
     * @return string the fully qualified class name of the original exception
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Returns the original exception's stack trace filtered to exclude internal PhpSpec
     * and vendor frames, keeping only user code.
     *
     * @return array stack frames from user code only
     */
    public function getFilteredTrace(): array
    {
        $trace = $this->original->getTrace();
        $filtered = [];
        foreach ($trace as $frame) {
            if (!isset($frame['file'])) {
                continue;
            }
            if (str_contains($frame['file'], 'src/PhpSpec/')) {
                continue;
            }
            if (str_contains($frame['file'], 'vendor/')) {
                continue;
            }
            $filtered[] = $frame;
        }
        return $filtered;
    }
}
