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

namespace PhpSpec\Specification;

use PhpSpec\CodeGeneration\SurroundingCode;
use Throwable;

/**
 * Wraps an exception thrown during example execution, providing access to
 * surrounding source code, the original exception type, and a filtered stack trace.
 */
final class ExampleError extends \Exception
{
    /** @var string fully qualified class name of the original exception */
    private string $type;

    /**
     * @param string $message error message
     * @param Throwable $original original exception from the example
     */
    public function __construct(protected $message, protected Throwable $original)
    {
        $this->line = $original->getLine();
        $this->file = $original->getFile();
        $this->type = get_class($original);
    }

    /**
     * Returns source code lines surrounding the error location.
     *
     * @param int $before number of lines before the error line
     * @param int $after number of lines after the error line
     * @return array lines of source code with line numbers as keys
     */
    public function getSurroundingCode(int $before = 3, int $after = 3): array
    {
        $surroundingCode = new SurroundingCode($this->original->getFile(), $this->original->getLine(), $before, $after);
        return $surroundingCode->toArray();
    }

    /**
     * Returns the class name of the original exception.
     *
     * @return string fully qualified class name
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Returns the stack trace filtered to exclude PhpSpec internals and vendor frames.
     *
     * @return array stack frames from user spec code only
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
            if (str_contains($frame['file'], 'functions.php') && !str_contains($frame['file'], 'spec/')) {
                continue;
            }
            $filtered[] = $frame;
        }
        return $filtered;
    }
}
