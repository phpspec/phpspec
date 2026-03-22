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

    /**
     * @param OutputInterface $inner the underlying Symfony console output to delegate to
     * @param int $scrollBottom the last row of the scrollable content region
     * @param int $inputRow the row number reserved for user input
     * @param int $dividerRow the row number for the horizontal divider line
     * @param int $width the terminal width in columns
     */
    public function __construct(
        private readonly OutputInterface $inner,
        private readonly int $scrollBottom,
        private readonly int $inputRow,
        private readonly int $dividerRow,
        private readonly int $width,
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
     * Moves cursor to the input row for readline. Redraws the bottom divider.
     */
    public function prepareForInput(): void
    {
        $divider = str_repeat("\u{2500}", $this->width);
        $this->inner->write(sprintf(
            "\033[%d;1H\033[K\033[2m%s\033[0m\033[%d;1H\033[K",
            $this->dividerRow,
            $divider,
            $this->inputRow,
        ));
        $this->cursorInContent = false;
    }

    /**
     * Echoes the user's input as a quoted line in the scroll region.
     */
    public function echoInput(string $input): void
    {
        $this->ensureInContent();
        $this->inner->writeln("  <options=bold>\u{2502}</> <fg=gray>$input</>");
    }

    /**
     * Clears the input row after readline returns. Next write() will reposition into the scroll region.
     */
    public function returnToContent(): void
    {
        $this->inner->write(sprintf("\033[%d;1H\033[K", $this->inputRow));
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
            $this->inner->write(sprintf("\033[%d;1H", $this->scrollBottom));
            $this->cursorInContent = true;
        }
    }
}
