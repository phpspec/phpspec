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
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 * What guard concluded, said once and read by everyone: the console line, the
 * exit code and the agent document all derive from this, so they cannot
 * disagree about whether the cycle held.
 *
 * A violation is shown, not merely announced: the lines themselves, marked as
 * the change left them, because the answer to "what did I fail to specify" is
 * the code in front of you.
 */
final readonly class Verdict
{
    /** How much of the surrounding code a violation is shown in. */
    private const CONTEXT = 2;

    /**
     * @param list<Violation> $violations
     */
    public function __construct(
        private array $violations,
        private Filesystem $filesystem,
        private string $baseDir = '.',
    ) {}

    public static function clean(): self
    {
        return new self([], new \PhpSpec\RealFilesystem());
    }

    public function held(): bool
    {
        return $this->violations === [];
    }

    /**
     * @return list<Violation>
     */
    public function violations(): array
    {
        return $this->violations;
    }

    /**
     * Writes the verdict for a person: what broke, the lines that broke it,
     * and what to do next.
     */
    public function render(OutputInterface $output): void
    {
        if ($this->held()) {
            return;
        }

        $output->writeln('');
        $output->writeln('<fg=red;options=bold>Guard Violation: the last change violates the TDD cycle.</>');

        foreach ($this->violations as $violation) {
            $output->writeln('');
            foreach ($this->window($violation) as $number => $text) {
                $marked = in_array($number, $violation->lines, true);
                $output->writeln(sprintf(
                    ' %s%4d %s %s',
                    $marked ? '<fg=red>' : '<fg=gray>',
                    $number,
                    $marked ? '+' : ' ',
                    $text . '</>',
                ));
            }

            $output->writeln('');
            $output->writeln('<fg=red>' . $violation->summary() . '</>');
            $output->writeln('<fg=yellow>' . $violation->remedy() . '</>');
        }
    }

    /**
     * The verdict as data, for the agent document.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'held' => $this->held(),
            'violations' => array_map(static fn(Violation $violation) => $violation->toArray(), $this->violations),
        ];
    }

    /**
     * The offending lines with a little of what surrounds them, so the reader
     * can see what the change was part of.
     *
     * @return array<int, string>
     */
    private function window(Violation $violation): array
    {
        $path = rtrim($this->baseDir, '/') . '/' . ltrim($violation->file, './');
        if (!$this->filesystem->exists($path)) {
            return [];
        }

        if ($violation->lines === []) {
            return [];
        }

        // Without their endings: the renderer adds its own, and a line that
        // kept one would print with a blank line after it.
        $lines = array_map(
            static fn(string $line) => rtrim($line, "\r\n"),
            array_values($this->filesystem->readLines($path)),
        );
        $first = max(1, min($violation->lines) - self::CONTEXT);
        $last = min(count($lines), max($violation->lines) + self::CONTEXT);

        $window = [];
        for ($number = $first; $number <= $last; $number++) {
            $window[$number] = $lines[$number - 1] ?? '';
        }

        return $window;
    }
}
