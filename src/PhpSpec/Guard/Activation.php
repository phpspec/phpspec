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

/**
 * @internal
 * Turns guard on in the project's own config file.
 *
 * The file is edited rather than rewritten: a project's config is something a
 * person wrote, with their comments and their ordering, and a tool that hands
 * it back re-dumped has taken something away. So the guard block is appended,
 * or its status line changed where one already exists, and everything else is
 * left exactly as it was found.
 */
final readonly class Activation
{
    /** In the order the configuration itself prefers them. */
    private const CANDIDATES = ['phpspec.yaml', 'phpspec.yml', 'phpspec.json', 'phpspec.php'];

    public function __construct(
        private Filesystem $filesystem,
        private string $baseDir = '.',
    ) {}

    /**
     * Turns guard on, and says which file now says so. Null when the project
     * keeps its configuration somewhere this cannot safely edit, in which case
     * the caller tells the reader what to add by hand.
     */
    public function turnOn(): ?string
    {
        $existing = $this->existing();

        if ($existing === null) {
            $path = $this->path('phpspec.yml');
            $this->filesystem->write($path, "guard:\n  status: active\n");

            return $path;
        }

        // JSON carries no comments, so re-encoding takes nothing away; PHP is
        // code, and rewriting somebody's code is not this command's business.
        if (str_ends_with($existing, '.json')) {
            $this->filesystem->write($existing, $this->activatedJson($this->filesystem->read($existing)));

            return $existing;
        }

        if (!str_ends_with($existing, '.yaml') && !str_ends_with($existing, '.yml')) {
            return null;
        }

        $this->filesystem->write($existing, $this->activated($this->filesystem->read($existing)));

        return $existing;
    }

    /**
     * The same for a JSON config: the guard block gains its status, and every
     * other key keeps its place.
     */
    private function activatedJson(string $config): string
    {
        $decoded = json_decode($config, true);
        $decoded = is_array($decoded) ? $decoded : [];
        $guard = isset($decoded['guard']) && is_array($decoded['guard']) ? $decoded['guard'] : [];
        $guard['status'] = 'active';
        $decoded['guard'] = $guard;

        return (string) json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    }

    /**
     * The config's text with guard on: the status changed where the project
     * already has a guard block, and the block appended where it does not.
     */
    private function activated(string $config): string
    {
        $lines = explode("\n", $config);
        $guard = null;

        foreach ($lines as $number => $line) {
            if (preg_match('/^guard:\s*$/', $line) === 1) {
                $guard = $number;
                break;
            }
        }

        if ($guard === null) {
            return rtrim($config, "\n") . "\n\nguard:\n  status: active\n";
        }

        // Inside the block: the indented lines that follow it, up to the next
        // top-level key.
        for ($number = $guard + 1; $number < count($lines); $number++) {
            if (trim($lines[$number]) !== '' && !str_starts_with($lines[$number], ' ')) {
                break;
            }

            if (preg_match('/^(\s+)status:\s*\S+\s*$/', $lines[$number], $matches) === 1) {
                $lines[$number] = $matches[1] . 'status: active';

                return implode("\n", $lines);
            }
        }

        array_splice($lines, $guard + 1, 0, ['  status: active']);

        return implode("\n", $lines);
    }

    /**
     * The config file this project already keeps, or null when it keeps none.
     */
    private function existing(): ?string
    {
        foreach (self::CANDIDATES as $candidate) {
            $path = $this->path($candidate);
            if ($this->filesystem->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function path(string $file): string
    {
        return rtrim($this->baseDir, '/') . '/' . $file;
    }
}
