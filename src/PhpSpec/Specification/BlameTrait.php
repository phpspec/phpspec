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

/**
 * @internal
 * Where an error is acted on. An error thrown inside PhpSpec itself, in a
 * double's generated code or in a vendor library is not fixed where it was
 * thrown: the first frame of the user's own code that led there is the line
 * to look at, and the code shown around the error is that line's.
 */
trait BlameTrait
{
    /**
     * The file and line a reader should look at: the site when it is the
     * user's code, else the nearest frame of the user's code; null when the
     * user's code is nowhere on the way, as when an example's arguments could
     * not be resolved before its body ran.
     *
     * @return array{file: string, line: int}|null
     */
    public function blame(): ?array
    {
        if ($this->file !== '' && self::isUsersCode($this->file)) {
            return ['file' => $this->file, 'line' => $this->line];
        }

        foreach ($this->getFilteredTrace() as $frame) {
            if (isset($frame['file'], $frame['line'])) {
                return ['file' => $frame['file'], 'line' => (int) $frame['line']];
            }
        }

        return null;
    }

    /**
     * Returns source code lines surrounding the blamed line.
     *
     * @param int $before number of lines before the line
     * @param int $after number of lines after the line
     * @return array<int, string> lines of source code with line numbers as keys
     */
    public function getSurroundingCode(int $before = 3, int $after = 3): array
    {
        if ($this->file === '') {
            return [];
        }

        $blame = $this->blame() ?? ['file' => $this->file, 'line' => $this->line];

        return (new SurroundingCode($blame['file'], $blame['line'], $before, $after))->toArray();
    }

    /**
     * The frames of the user's own code, PhpSpec's and vendor's left out.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFilteredTrace(): array
    {
        $filtered = [];

        foreach ($this->original->getTrace() as $frame) {
            if (isset($frame['file']) && self::isUsersCode($frame['file'])) {
                $filtered[] = $frame;
            }
        }

        return $filtered;
    }

    private static function isUsersCode(string $file): bool
    {
        return !str_contains($file, 'src/PhpSpec/')
            && !str_contains($file, 'vendor/')
            && !str_contains($file, "eval()'d code")
            && !str_ends_with($file, '/bin/phpspec')
            && !(str_contains($file, 'functions.php') && !str_contains($file, 'spec/'));
    }
}
