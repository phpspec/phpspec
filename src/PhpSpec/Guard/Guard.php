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

namespace PhpSpec\Guard;

use PhpSpec\Filesystem;
use PhpSpec\Source\Members;

/**
 * @internal
 * The one deterministic question guard asks: of the logic this session wrote,
 * which of it does no example reach?
 *
 * Changed and covered is fine, which is why a refactor passes for free and
 * needs no mode of its own. Changed and uncovered is the violation. Unchanged
 * is never anybody's business here, however untested it is: guard judges the
 * session, not the codebase.
 */
final readonly class Guard
{
    public function __construct(
        private Filesystem $filesystem,
        private string $baseDir = '.',
    ) {}

    /**
     * @return list<Violation>
     */
    public function violations(Delta $delta, Coverage $coverage): array
    {
        $violations = [];

        foreach ($delta->all() as $file => $lines) {
            foreach ($this->byMember($file, $this->logic($file, $lines, $coverage)) as $member => $logic) {
                $unreached = array_values(array_filter($logic, static fn(int $line) => !$coverage->covers($file, $line)));

                if ($unreached === []) {
                    continue;
                }

                $name = $member === '' ? null : $member;

                // Whether anything at all reached this member decides how it
                // is put: nothing written for it yet is a different thing to
                // say than an example that does not go far enough.
                $violations[] = count($unreached) === count($logic)
                    ? Violation::untestedLogic($file, $unreached, $name)
                    : Violation::partlyReached($file, $unreached, $name);
            }
        }

        return $violations;
    }

    /**
     * The changed lines that carry logic, reached or not.
     *
     * @param list<int> $lines
     * @return list<int>
     */
    private function logic(string $file, array $lines, Coverage $coverage): array
    {
        // A file the run never loaded has no coverage to consult, which is the
        // shape of a class written and never specified: the source says which
        // of its lines are logic, and none of them were reached.
        $statements = $coverage->knows($file) ? null : Statements::in($this->lines($file));

        return array_values(array_filter($lines, static function (int $line) use ($file, $coverage, $statements): bool {
            if ($statements !== null) {
                return in_array($line, $statements, true);
            }

            return $coverage->isExecutable($file, $line);
        }));
    }

    /**
     * The untested lines grouped by the member they sit in, so one report
     * names one thing to fix. Lines outside any member share the empty group.
     *
     * @param list<int> $lines
     * @return array<string, list<int>>
     */
    private function byMember(string $file, array $lines): array
    {
        if ($lines === []) {
            return [];
        }

        $members = Members::in(implode("\n", $this->lines($file)));
        $grouped = [];

        foreach ($lines as $line) {
            $grouped[$members->at($line) ?? ''][] = $line;
        }

        return $grouped;
    }

    /**
     * The file's lines, without their endings: they are rejoined to be
     * tokenised, and a line that kept its newline would be counted twice,
     * putting every member a line further down than it is.
     *
     * @return list<string>
     */
    private function lines(string $file): array
    {
        $path = rtrim($this->baseDir, '/') . '/' . ltrim($file, './');

        if (!$this->filesystem->exists($path)) {
            return [];
        }

        return array_map(
            static fn(string $line) => rtrim($line, "\r\n"),
            array_values($this->filesystem->readLines($path)),
        );
    }
}
