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

use PhpSpec\Console\Command\Refactor\Diff;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

/**
 * @internal
 * Output helpers for the pair programming REPL session.
 *
 * Manages a fixed-input-at-bottom terminal layout using ANSI scroll regions:
 * header pinned at top, output scrolling in the middle, input line at the bottom.
 */
final class PairOutput
{
    private int $width;
    private int $height;
    private ?ScrollRegionOutput $scrollOutput = null;
    private bool $aiAvailable = false;
    private ?StatusBar $statusBar = null;

    /**
     * @param OutputInterface $output the underlying Symfony console output
     */
    public function __construct(private readonly OutputInterface $output)
    {
        $terminal = new Terminal();
        $this->width = $terminal->getWidth() ?: 80;
        $this->height = $terminal->getHeight() ?: 24;
    }

    /**
     * Configures the pinned footer shown below the input: the working directory,
     * the AI status, and a live role indicator. Called once, before the REPL
     * starts. Without it the layout falls back to just the input divider.
     *
     * @param string $workingDir the working directory to show (already abbreviated)
     * @param bool $aiAvailable whether an AI provider is configured
     * @param string|null $provider the configured provider name, when AI is on
     * @param RoleState $roleState the live pairing role, read on each redraw
     */
    public function configureStatus(string $workingDir, bool $aiAvailable, ?string $provider, RoleState $roleState): void
    {
        $this->statusBar = new StatusBar($workingDir, $aiAvailable, $provider, $roleState);
    }

    /**
     * Returns the scroll region output decorator for passing to formatters.
     * Falls back to the raw output if setupLayout() hasn't been called.
     */
    public function getOutput(): OutputInterface
    {
        return $this->scrollOutput ?? $this->output;
    }

    /**
     * Sets up the fixed terminal layout: header, scroll region, bottom divider, input line.
     *
     * @param bool $aiAvailable whether an AI provider is configured
     */
    public function setupLayout(bool $aiAvailable = false): void
    {
        if ($aiAvailable) {
            $this->aiAvailable = true;
        }
        $terminal = new Terminal();
        $this->width = $terminal->getWidth() ?: 80;
        $this->height = $terminal->getHeight() ?: 24;

        // Reserve rows at the bottom: a divider above the input, the input, and
        // — when a status bar is configured — a divider below it plus the two
        // footer lines. Otherwise fall back to the input divider alone.
        $reserved = $this->statusBar !== null ? 5 : 2;
        $scrollBottom = max(1, $this->height - $reserved);

        // Reset scroll region and clear entire screen
        $this->output->write("\033[r\033[2J\033[H");

        // Set scroll region (rows 1 through scrollBottom) — header scrolls away
        $this->output->write(sprintf("\033[1;%dr", $scrollBottom));
        $this->output->write("\033[1;1H");

        $this->output->writeln('');
        $this->output->writeln('<fg=bright-blue;options=bold>  PhpSpec Pair Programming Mode</>');
        $this->output->writeln('');
        $divider = str_repeat("\u{2500}", $this->width);
        $this->output->writeln("\033[34m$divider\033[0m");

        $this->scrollOutput = $this->statusBar !== null
            ? new ScrollRegionOutput(
                $this->output,
                $scrollBottom,
                $this->height - 3,
                $this->height - 4,
                $this->width,
                $this->height - 2,
                $this->height - 1,
                $this->height,
                $this->statusBar,
            )
            : new ScrollRegionOutput(
                $this->output,
                $scrollBottom,
                $this->height,
                $this->height - 1,
                $this->width,
            );
    }

    /**
     * Moves cursor to the input row for readline.
     */
    public function prepareForInput(): void
    {
        // Lazy init or resize detection
        $newHeight = (new Terminal())->getHeight() ?: 24;
        if ($this->scrollOutput === null || $newHeight !== $this->height) {
            $this->setupLayout($this->aiAvailable);
        }

        $this->scrollOutput?->prepareForInput();
    }

    /**
     * Returns cursor to the scroll region after readline.
     */
    public function returnToContent(): void
    {
        $this->scrollOutput?->returnToContent();
    }

    /**
     * Displays a goodbye message when exiting the REPL.
     * Resets the scroll region so the terminal is back to normal.
     */
    public function showGoodbye(): void
    {
        // Reset scroll region, move to bottom
        $this->output->write(sprintf("\033[r\033[%d;1H", $this->height));
        $this->output->writeln('');
        $this->output->writeln('<fg=bright-blue>  Goodbye!</>');
        $this->output->writeln('');
    }

    /**
     * Clears the screen by re-setting up the full layout.
     */
    public function clearScreen(): void
    {
        $this->setupLayout($this->aiAvailable);
    }

    /**
     * Echoes the user's input as a quoted line in the scroll region.
     */
    public function echoInput(string $input): void
    {
        $this->getOutput()->writeln("  <options=bold>\u{2502}</> <fg=gray>{$input}</>");
    }

    /**
     * Displays a success message in green.
     */
    public function success(string $message): void
    {
        $this->getOutput()->writeln("  <fg=green>{$message}</>");
    }

    /**
     * Displays an error message in red.
     */
    public function error(string $message): void
    {
        $this->getOutput()->writeln("  <fg=red>{$message}</>");
    }

    /**
     * Displays a file's contents with line numbers and a label.
     *
     * @param string $path the file path to display as a header
     * @param string $content the file contents to render with line numbers
     * @param bool $isNew whether this is a newly created file
     */
    public function fileDisplay(string $path, string $content, bool $isNew): void
    {
        $out = $this->getOutput();
        $label = $isNew ? '[NEW FILE]' : '[MODIFIED]';
        $out->writeln('');
        $out->writeln("  <fg=yellow>$label</> <fg=white>$path</>");
        $out->writeln('');

        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            $lineNum = str_pad((string) ($i + 1), 4, ' ', STR_PAD_LEFT);
            $out->writeln("  <fg=green>$lineNum + </>$line");
        }
        $out->writeln('');
    }

    /**
     * Displays a unified diff between old and new file content.
     */
    public function fileDiff(string $path, string $oldContent, string $newContent): void
    {
        $out = $this->getOutput();
        $out->writeln('');
        $out->writeln("  <fg=yellow>[MODIFIED]</> <fg=white>$path</>");
        $out->writeln('');

        $oldLines = explode("\n", $oldContent);
        $newLines = explode("\n", $newContent);
        $diff = Diff::compute($oldLines, $newLines);

        $out->writeln(Diff::format($diff));
        $out->writeln('');
    }
}
