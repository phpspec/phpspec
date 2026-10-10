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
 * Takes PHP's warnings, deprecations and notices while a piece of the run
 * goes, keeping each with where it was raised rather than letting it reach
 * the terminal. A deprecation a library raised for another library calling it
 * is left out: nothing the project changes in its own code makes it go away.
 */
final class CapturedNotes
{
    private const LEVELS = E_WARNING | E_NOTICE | E_DEPRECATED | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED;

    /** @var list<array{severity: int, message: string, file: string, line: int}> */
    private array $notes = [];

    public function __construct(private readonly OwnCode $own) {}

    public function listen(): void
    {
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if (!$this->deprecationBetweenLibraries($severity, $file, $line, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS))) {
                $this->notes[] = ['severity' => $severity, 'message' => $message, 'file' => $file, 'line' => $line];
            }

            return true;
        }, self::LEVELS);
    }

    public function stop(): void
    {
        restore_error_handler();
    }

    /**
     * @return list<array{severity: int, message: string, file: string, line: int}>
     */
    public function notes(): array
    {
        return $this->notes;
    }

    /**
     * Whether a deprecation was raised in a library's code for code that is
     * not the project's either. The code that raised it is where it was
     * raised, or, for one announced through trigger_deprecation(), the code
     * that announced it; the code that called it is the next place out.
     *
     * @param list<array{function?: string, file?: string, line?: int}> $frames the handler's own first, outward from where the deprecation was raised
     */
    private function deprecationBetweenLibraries(int $severity, string $file, int $line, array $frames): bool
    {
        if ($severity !== E_DEPRECATED && $severity !== E_USER_DEPRECATED) {
            return false;
        }

        $places = $this->placesOutwardFrom($file, $line, $frames);
        $raiser = $places[0] ?? $file;
        $caller = $places[1] ?? null;

        return !$this->own->holds($raiser) && ($caller === null || !$this->own->holds($caller));
    }

    /**
     * The files of the code a deprecation passed through, from the code that
     * raised it outward, the machinery that raised it left out.
     *
     * @param list<array{function?: string, file?: string, line?: int}> $frames
     * @return list<string>
     */
    private function placesOutwardFrom(string $file, int $line, array $frames): array
    {
        $announced = array_search('trigger_deprecation', array_column($frames, 'function'), true);
        $outward = is_int($announced) ? array_slice($frames, $announced) : $frames;
        $places = is_int($announced) ? [] : [$file];

        foreach ($outward as $frame) {
            if (isset($frame['file']) && ($frame['file'] !== $file || ($frame['line'] ?? null) !== $line)) {
                $places[] = $frame['file'];
            }
        }

        return $places;
    }
}
