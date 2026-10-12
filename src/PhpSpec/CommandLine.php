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

/**
 * @internal
 * A phpspec command line as PhpSpec hands it to a reader to run: the binary
 * as it was invoked, the command, and the options that set up the same
 * project, each argument a shell would split or expand single-quoted.
 */
final readonly class CommandLine
{
    /**
     * @param string|null $binary the phpspec binary to run, or null for the arguments alone
     * @param list<string> $options the options every line carries, so it runs against the same project
     */
    public function __construct(
        private ?string $binary = 'phpspec',
        private array $options = [],
    ) {}

    /**
     * The command line this process was started by: its binary as it was
     * typed, named from the project root, and the config it was named.
     */
    public static function invoked(Configuration $config): self
    {
        $binary = $_SERVER['argv'][0] ?? null;

        return new self(is_string($binary) && $binary !== '' ? ProjectRoot::here()->relative($binary) : 'phpspec', $config->childOptions());
    }

    public function for(string ...$arguments): string
    {
        $words = array_map($this->word(...), [...$arguments, ...$this->options]);

        return implode(' ', $this->binary === null ? $words : [$this->word($this->binary), ...$words]);
    }

    /**
     * An argument as a POSIX shell reads it back unchanged: bare when it holds
     * nothing a shell splits or expands, else in single quotes.
     */
    private function word(string $argument): string
    {
        if (preg_match('~^[A-Za-z0-9_@%+=:,./-]+$~', $argument) === 1) {
            return $argument;
        }

        return "'" . str_replace("'", "'\\''", $argument) . "'";
    }
}
