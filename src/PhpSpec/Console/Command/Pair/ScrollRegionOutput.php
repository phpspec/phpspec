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

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 * Decorates an OutputInterface to manage cursor positioning within
 * an ANSI scroll region. All write/writeln calls are redirected
 * into the scrollable content area, while prepareForInput() moves
 * the cursor to the fixed input row at the bottom.
 */
final class ScrollRegionOutput implements OutputInterface
{
    private bool $cursorInContent = true;

    /** @var int the rows the input has taken from the content, above its own */
    private int $inputGrowth = 0;

    /**
     * @param OutputInterface $inner the underlying Symfony console output to delegate to
     * @param int $scrollBottom the last row of the scrollable content region
     * @param int $inputRow the row number reserved for user input
     * @param int $topDividerRow the row for the divider above the input line
     * @param int $width the terminal width in columns
     * @param int|null $bottomDividerRow the row for the divider below the input
     * @param int|null $statusRow the row for the status line
     * @param int|null $roleRow the row for the role/hint line
     * @param StatusBar|null $statusBar renders the two footer lines
     */
    public function __construct(
        private readonly OutputInterface $inner,
        private readonly int $scrollBottom,
        private readonly int $inputRow,
        private readonly int $topDividerRow,
        private readonly int $width,
        private readonly ?int $bottomDividerRow = null,
        private readonly ?int $statusRow = null,
        private readonly ?int $roleRow = null,
        private readonly ?StatusBar $statusBar = null,
    ) {}

    /**
     * {@inheritdoc}
     * @param string|iterable<string> $messages
     */
    public function write(string|iterable $messages, bool $newline = false, int $options = 0): void
    {
        $this->ensureInContent();
        $this->inner->write($messages, $newline, $options);
    }

    /**
     * {@inheritdoc}
     * @param string|iterable<string> $messages
     */
    public function writeln(string|iterable $messages, int $options = 0): void
    {
        $this->ensureInContent();
        $this->inner->writeln($messages, $options);
    }

    /**
     * Moves the cursor to the input row for readline, redrawing the pinned
     * chrome: a blue divider above the input, and — when configured — a blue
     * divider below it plus the two status-bar footer lines.
     */
    public function prepareForInput(): void
    {
        $out = $this->regrown(0) . sprintf("\033[%d;1H\033[K%s", $this->topDividerRow, $this->divider());

        if ($this->bottomDividerRow !== null && $this->statusRow !== null
            && $this->roleRow !== null && $this->statusBar !== null
        ) {
            [$status, $role] = $this->statusBar->lines($this->width);
            $out .= sprintf("\033[%d;1H\033[K%s", $this->bottomDividerRow, $this->divider());
            $out .= sprintf("\033[%d;1H\033[K%s", $this->statusRow, $status);
            $out .= sprintf("\033[%d;1H\033[K%s", $this->roleRow, $role);
        }

        $out .= sprintf("\033[%d;1H\033[K", $this->inputRow);

        $this->inner->write($out);
        $this->cursorInContent = false;
    }

    /**
     * Echoes the user's input as a quoted line in the scroll region.
     */
    public function echoInput(string $input): void
    {
        $this->ensureInContent();

        foreach (explode("\n", $input) as $line) {
            $this->inner->writeln("  <options=bold>\u{2502}</> <fg=gray>$line</>");
        }
    }

    /**
     * Gives the line being typed as many rows as it takes, up to half the
     * content's: the rows above the input row, so the row being typed on
     * stays at the bottom. The content scrolls up out of the rows the input
     * takes, and back down into the rows it gives back, and the top divider
     * moves with the input's first row.
     */
    public function resizeInput(int $rows): InputArea
    {
        $growth = min(max(0, $rows - 1), max(0, intdiv($this->scrollBottom, 2) - 1));

        if ($growth !== $this->inputGrowth) {
            $out = $this->regrown($growth);
            $out .= sprintf("\033[%d;1H\033[K%s", $this->topDividerRow - $growth, $this->divider());
            $this->inner->write($out);
            $this->cursorInContent = false;
        }

        return new InputArea($this->inputRow - $growth, $growth + 1);
    }

    /**
     * Clears the input after readline returns, giving the content back the
     * rows a long line took. Next write() will reposition into the scroll region.
     */
    public function returnToContent(): void
    {
        $this->inner->write($this->regrown(0) . sprintf("\033[%d;1H\033[K", $this->inputRow));
        $this->cursorInContent = false;
    }

    /** {@inheritdoc} */
    public function setVerbosity(int $level): void
    {
        $this->inner->setVerbosity($level);
    }

    /** {@inheritdoc} */
    public function getVerbosity(): int
    {
        return $this->inner->getVerbosity();
    }

    /** {@inheritdoc} */
    public function isSilent(): bool
    {
        return $this->inner->isSilent();
    }

    /** {@inheritdoc} */
    public function isQuiet(): bool
    {
        return $this->inner->isQuiet();
    }

    /** {@inheritdoc} */
    public function isVerbose(): bool
    {
        return $this->inner->isVerbose();
    }

    /** {@inheritdoc} */
    public function isVeryVerbose(): bool
    {
        return $this->inner->isVeryVerbose();
    }

    /** {@inheritdoc} */
    public function isDebug(): bool
    {
        return $this->inner->isDebug();
    }

    /** {@inheritdoc} */
    public function setDecorated(bool $decorated): void
    {
        $this->inner->setDecorated($decorated);
    }

    /** {@inheritdoc} */
    public function isDecorated(): bool
    {
        return $this->inner->isDecorated();
    }

    /** {@inheritdoc} */
    public function setFormatter(OutputFormatterInterface $formatter): void
    {
        $this->inner->setFormatter($formatter);
    }

    /** {@inheritdoc} */
    public function getFormatter(): OutputFormatterInterface
    {
        return $this->inner->getFormatter();
    }

    /**
     * Repositions cursor into the scroll region if it was moved to the input row.
     */
    private function ensureInContent(): void
    {
        if (!$this->cursorInContent) {
            $this->inner->write(sprintf("\033[%d;1H", $this->scrollBottom - $this->inputGrowth));
            $this->cursorInContent = true;
        }
    }

    /**
     * Moves the bottom of the scroll region to leave the input the rows it
     * now takes. Taking rows scrolls the content up out of them first; giving
     * them back scrolls it down into them, so no line of content is covered.
     */
    private function regrown(int $growth): string
    {
        if ($growth === $this->inputGrowth) {
            return '';
        }

        $out = '';

        if ($growth > $this->inputGrowth) {
            $out .= sprintf("\033[%d;1H", $this->scrollBottom - $this->inputGrowth) . str_repeat("\n", $growth - $this->inputGrowth);
        }

        $out .= sprintf("\033[1;%dr", $this->scrollBottom - $growth);

        if ($growth < $this->inputGrowth) {
            $out .= "\033[1;1H" . str_repeat("\033M", $this->inputGrowth - $growth);
        }

        for ($row = $this->topDividerRow - max($growth, $this->inputGrowth); $row < $this->topDividerRow - $growth; $row++) {
            $out .= sprintf("\033[%d;1H\033[K", $row);
        }

        $this->inputGrowth = $growth;

        return $out;
    }

    private function divider(): string
    {
        return "\033[36m" . str_repeat("\u{2500}", $this->width) . "\033[0m";
    }
}
