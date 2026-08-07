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

/**
 * @internal
 * Something guard found wrong with the change, and what to do about it.
 *
 * The wording is settled where the finding is made rather than where it is
 * read, so a reader of a violation never has to know which kind it was.
 */
final readonly class Violation
{
    /**
     * @param string $file the file, project-relative
     * @param list<int> $lines the lines it concerns
     * @param string|null $member the member they sit in, when the source names one
     */
    private function __construct(
        public string $file,
        public array $lines,
        public ?string $member,
        public string $summary,
        public string $remedy,
    ) {}

    /**
     * Logic this session wrote that no example reaches.
     *
     * Named by its member where the source says which one it is, because
     * "App\Basket::applyCoupon is untested" is something a reader can act on
     * and "src/App/Basket.php lines 34 to 37" is something they have to go and
     * look up.
     *
     * @param list<int> $lines
     */
    public static function untestedLogic(string $file, array $lines, ?string $member = null): self
    {
        $at = $file . ':' . ($lines[0] ?? 0);

        return new self(
            $file,
            $lines,
            $member,
            sprintf('New logic in %s is untested.', $member ?? $at),
            $member !== null
                ? sprintf('Write an example for %s, then make it pass.', $member)
                : sprintf('Write an example that reaches %s, then make it pass.', $file),
        );
    }

    /**
     * Logic this session wrote that an example reaches part of, but not all.
     *
     * Said differently from logic nothing reaches, because the reader has just
     * written the example they were asked for: telling them again to write one
     * reads as though nothing they did counted, when in fact only the lines
     * still named here are left.
     *
     * @param list<int> $lines the lines still unreached
     */
    public static function partlyReached(string $file, array $lines, ?string $member = null): self
    {
        $at = $file . ':' . ($lines[0] ?? 0);

        return new self(
            $file,
            $lines,
            $member,
            sprintf('Part of the new logic in %s is still unreached.', $member ?? $at),
            $member !== null
                ? sprintf('Extend your examples for %s to reach %s.', $member, self::asLines($lines))
                : sprintf('Extend your examples to reach %s of %s.', self::asLines($lines), $file),
        );
    }

    /**
     * The lines as a person would say them, so a remedy can name them without
     * the reader having to count.
     *
     * @param list<int> $lines
     */
    private static function asLines(array $lines): string
    {
        if (count($lines) === 1) {
            return 'line ' . $lines[0];
        }

        $last = array_pop($lines);

        return sprintf('lines %s and %d', implode(', ', $lines), $last);
    }

    /**
     * @return array{file: string, lines: list<int>, member: string|null, remedy: string}
     */
    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'lines' => $this->lines,
            'member' => $this->member,
            'remedy' => $this->remedy,
        ];
    }
}
