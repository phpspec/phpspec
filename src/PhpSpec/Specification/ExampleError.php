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

use Throwable;

/**
 * @internal
 * Wraps an exception thrown during example execution, providing access to
 * surrounding source code, the original exception type, and a filtered stack trace.
 */
final class ExampleError extends \Exception
{
    use BlameTrait;

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
     * An error rebuilt from a report: its message and type, and the site and
     * the frames of the user's code when the report carried them, as a
     * parallel worker's does. None is made up when it did not: a site taken
     * from where it was rebuilt would read like a location and re-run like
     * nonsense.
     *
     * @param array<int, array<string, mixed>> $trace the frames of the user's code, innermost first
     */
    public static function fromReport(string $message, string $type, string $file = '', int $line = 0, array $trace = []): self
    {
        $error = new self($message, new \RuntimeException($message));
        $error->file = $file;
        $error->line = $line;
        $error->type = $type;
        $error->reportedTrace = $trace;

        return $error;
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
     * The class this error says does not exist, or null when it is about
     * anything else.
     */
    public function missingClass(): ?string
    {
        if (preg_match('/^Class "([^"]+)" not found$/', $this->message, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

}
