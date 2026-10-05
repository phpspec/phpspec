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
     * An error rebuilt from a report that carried its message and type and
     * nothing else, as a parallel worker's does. It has no site: one made up
     * from where it was rebuilt would read like a location and re-run like
     * nonsense.
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
