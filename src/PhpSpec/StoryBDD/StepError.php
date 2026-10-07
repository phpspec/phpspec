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

use PhpSpec\Specification\BlameTrait;

/**
 * @internal
 * Wraps a throwable that occurred during step execution, preserving the original
 * exception's file, line, and class type for error reporting.
 */
final class StepError extends \Exception
{
    use BlameTrait;

    /** @var string the class name of the original throwable */
    private string $type;

    private \Throwable $original;

    /**
     * Wraps an original throwable, preserving its file and line for error reporting.
     *
     * @param string $message the error message
     * @param \Throwable $original the original throwable that caused the step failure
     */
    public function __construct(string $message, \Throwable $original)
    {
        parent::__construct($message);
        $this->original = $original;
        $this->line = $original->getLine();
        $this->file = $original->getFile();
        $this->type = get_class($original);
    }

    /**
     * An error rebuilt from a report that carried its message and type and
     * nothing else, as a parallel worker's does; it has no site.
     */
    public static function fromReport(string $message, string $type): self
    {
        $error = new self($message, new \RuntimeException($message));
        $error->file = '';
        $error->line = 0;
        $error->type = $type;

        return $error;
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

}
