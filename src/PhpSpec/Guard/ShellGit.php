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
 * Git as the command line answers it. Every question is asked with an argument
 * list rather than a string, so a path can never be read as an option or a
 * shell operator, and a git that is missing or angry answers "not a
 * repository" rather than throwing: guard degrades to comparing files itself.
 */
final class ShellGit implements Git
{
    /**
     * @param string $workingDir the project root every question is asked from
     */
    public function __construct(private readonly string $workingDir) {}

    public function isRepository(): bool
    {
        return $this->run(['rev-parse', '--is-inside-work-tree']) === 'true';
    }

    public function head(): ?string
    {
        return $this->run(['rev-parse', 'HEAD']);
    }

    public function diff(string $commit, array $paths): string
    {
        // No colour, no rename detection, and no context: guard wants the
        // numbers of the new lines, not something to read.
        return $this->run(['diff', '--no-color', '--no-renames', '--unified=0', $commit, '--', ...$paths]) ?? '';
    }

    public function untracked(array $paths): array
    {
        // Terminated with NULs rather than newlines: a path may contain a
        // newline, and one that arrives split in two matches no file at all.
        $listed = $this->run(['ls-files', '--others', '--exclude-standard', '-z', '--', ...$paths]);
        if ($listed === null) {
            return [];
        }

        return array_values(array_filter(explode("\0", $listed), static fn(string $line) => trim($line) !== ''));
    }

    /**
     * Runs one git command, returning its trimmed output, or null when git
     * could not answer.
     *
     * @param list<string> $arguments
     */
    private function run(array $arguments): ?string
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        // Paths as they are on disk. Git escapes anything outside ASCII by
        // default, and "src/caf\303\251.php" is a file guard would look for
        // and never find, so it would quietly stop judging it.
        $process = @proc_open(['git', '-c', 'core.quotePath=false', ...$arguments], $descriptors, $pipes, $this->workingDir);

        if (!is_resource($process)) {
            return null;
        }

        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        if (proc_close($process) !== 0) {
            return null;
        }

        $output = trim($output);

        return $output === '' ? null : $output;
    }
}
