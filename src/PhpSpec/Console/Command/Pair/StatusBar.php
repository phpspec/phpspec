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

namespace PhpSpec\Console\Command\Pair;

/**
 * @internal
 * Renders the two footer lines pinned below the pair-mode input: the working
 * directory and AI status, and a live role indicator drawn from the shared
 * RoleState. Output is raw ANSI (blue accents, dim text) so it can be written
 * straight to the fixed terminal rows outside the scroll region.
 */
final readonly class StatusBar
{
    private const BLUE = "\033[34m";
    private const DIM = "\033[2m";
    private const RESET = "\033[0m";
    private const MARKER = "\u{23F5}\u{23F5}";

    /**
     * @param string $workingDir the working directory to show (already abbreviated)
     * @param bool $aiAvailable whether the AI provider actually started
     * @param string|null $provider the configured provider name, when AI is on
     * @param RoleState $roleState the live pairing role, read at render time
     * @param string|null $unavailableReason why a configured provider could not start (drives the "unavailable" state)
     */
    public function __construct(
        private string $workingDir,
        private bool $aiAvailable,
        private ?string $provider,
        private RoleState $roleState,
        private ?string $unavailableReason = null,
    ) {}

    /**
     * The status line and the role/hint line, coloured with raw ANSI.
     *
     * @param int $width the terminal width, used to right-align the AI status
     * @return array{0: string, 1: string}
     */
    public function lines(int $width): array
    {
        return [$this->statusLine($width), $this->roleLine()];
    }

    /**
     * Replaces the home-directory prefix with ~ for a shorter display.
     */
    public static function abbreviateHome(string $path, string $home): string
    {
        return $home !== '' && str_starts_with($path, $home) ? '~' . substr($path, strlen($home)) : $path;
    }

    private function statusLine(int $width): string
    {
        $left = '  ' . $this->workingDir;
        $right = match (true) {
            $this->aiAvailable => sprintf('ai: on | provider: %s', $this->provider ?? '?'),
            $this->unavailableReason !== null => 'ai: unavailable',
            default => 'ai: off',
        };

        $gap = max(2, $width - mb_strlen($left) - mb_strlen($right) - 2);

        return self::DIM . $left . str_repeat(' ', $gap) . $right . '  ' . self::RESET;
    }

    private function roleLine(): string
    {
        $text = match (true) {
            $this->aiAvailable => sprintf('ai is %s (/swap to change)', $this->roleState->current()->aiIsDriver() ? 'driver' : 'navigator'),
            $this->unavailableReason !== null => 'ai provider could not start — type a prompt or /help to see why',
            default => 'add an ai: section to phpspec.yaml to pair with AI',
        };

        return self::BLUE . ' ' . self::MARKER . ' ' . self::RESET . self::DIM . $text . self::RESET;
    }
}
