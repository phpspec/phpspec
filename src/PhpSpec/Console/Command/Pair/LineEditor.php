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

use Closure;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 * A raw-mode line editor for the pair REPL. The cursor moves through the text
 * by character, by word, to either end of a line and up and down its rows;
 * typing inserts at it. Shift+Enter or Alt+Enter break the line, a paste
 * arrives whole, and Up on the first row or Down on the last walks the
 * history. The input can be seeded with a dim "ghost" suggestion (the next
 * command) that Right-arrow or Tab accepts, fish-shell style; typing
 * dismisses it.
 *
 * On a TTY a key loop drives it, with the terminal asked to report modified
 * keys apart and to bracket pastes; piped input falls back to reading a whole
 * line so scripted sessions keep working. Both the key reader and the line
 * reader are injectable, so specs drive it without a real terminal.
 */
final class LineEditor
{
    /** Bracketed paste on, and modified keys such as Shift+Enter reported apart from plain ones. */
    private const KEY_MODES_ON = "\033[?2004h\033[>1u";
    private const KEY_MODES_OFF = "\033[<u\033[?2004l";

    private readonly Closure $reader;

    private readonly ?Closure $keys;

    private readonly bool $detectTty;

    private readonly KeyDecoder $decoder;

    /**
     * @param PairOutput $output the pair screen to paint the input line into
     * @param Closure|null $reader reads one whole line (piped input); injectable
     * @param Closure|null $keys reads one raw key token (TTY input); injectable —
     *                           when given, key mode is used without touching the terminal
     */
    public function __construct(
        private readonly PairOutput $output,
        ?Closure $reader = null,
        ?Closure $keys = null,
    ) {
        $this->reader = $reader ?? fn(): ?string => $this->readStdinLine();
        $this->keys = $keys;
        $this->decoder = new KeyDecoder();
        // Only probe the real terminal when no input source was injected, so
        // specs behave the same whether run from a TTY or a pipe.
        $this->detectTty = $reader === null && $keys === null;
    }

    /**
     * Reads one line, optionally seeded with a dim suggestion the user accepts
     * with Right-arrow or Tab. Up/Down walk the given history from the first
     * and last rows. Returns the line, or null on EOF / cancel.
     *
     * @param list<string> $history earlier lines, oldest first, for Up/Down recall
     */
    public function readLine(string $prompt, ?string $suggestion = null, array $history = []): ?string
    {
        // Raw key mode needs stty, so Windows terminals use line mode.
        $keyCapable = DIRECTORY_SEPARATOR === '/' && $this->detectTty && stream_isatty(STDIN);

        if ($this->keys !== null || $keyCapable) {
            return $this->readByKey($prompt, $suggestion, $history);
        }

        return $this->readByLine($prompt);
    }

    /**
     * Line mode (piped input): paints the prompt and reads one whole line. The
     * suggestion is ignored — there is no cursor to preview it against.
     */
    private function readByLine(string $prompt): ?string
    {
        $this->output->rawOutput()->write($prompt, false, OutputInterface::OUTPUT_RAW);

        return ($this->reader)();
    }

    /**
     * Key mode (TTY): a per-keystroke loop over the line being typed. Ctrl-C
     * arrives as a key rather than a signal, so however the loop ends the
     * terminal is given back as it was.
     *
     * @param list<string> $history earlier lines, oldest first
     */
    private function readByKey(string $prompt, ?string $suggestion, array $history): ?string
    {
        $next = $this->keys;

        if ($next === null) {
            $next = (new TerminalKeys())->next(...);
            system('stty -icanon -echo -isig');
            $this->output->rawOutput()->write(self::KEY_MODES_ON, false, OutputInterface::OUTPUT_RAW);
        }

        $line = new TypedLine();
        $draft = $line;
        $ghost = ($suggestion !== null && $suggestion !== '') ? $suggestion : null;
        $recalled = count($history);

        try {
            $this->render($prompt, $line, $ghost);

            while (true) {
                $key = $this->decoder->decode($next());

                if ($key->name === KeyName::Enter) {
                    return $line->text;
                }

                if ($key->name === KeyName::Cancel || ($key->name === KeyName::EndOfInput && $line->text === '')) {
                    return null;
                }

                if ($key->name === KeyName::EndOfInput) {
                    return $line->text;
                }

                if ($key->name === KeyName::Ignored) {
                    continue;
                }

                if (in_array($key->name, [KeyName::Tab, KeyName::Right], true) && $ghost !== null && $line->text === '') {
                    $line = new TypedLine($ghost);
                    $ghost = null;
                } elseif ($key->name === KeyName::Up) {
                    $above = $this->wrapped($prompt, $line, $ghost)->cursorAbove();

                    if ($above !== null) {
                        $line = $line->at($above);
                    } elseif ($recalled > 0) {
                        $draft = $recalled === count($history) ? $line : $draft;
                        $line = new TypedLine($history[--$recalled]);
                        $ghost = null;
                    }
                } elseif ($key->name === KeyName::Down) {
                    $below = $this->wrapped($prompt, $line, $ghost)->cursorBelow();

                    if ($below !== null) {
                        $line = $line->at($below);
                    } elseif ($recalled < count($history)) {
                        $line = ++$recalled === count($history) ? $draft : new TypedLine($history[$recalled]);
                    }
                } else {
                    $line = $line->after($key);
                    $ghost = $line->text === '' ? $ghost : null;
                }

                $this->render($prompt, $line, $ghost);
            }
        } finally {
            if ($this->keys === null) {
                $this->output->rawOutput()->write(self::KEY_MODES_OFF, false, OutputInterface::OUTPUT_RAW);
                system('stty icanon echo isig');
            }
        }
    }

    /**
     * Repaints the line being typed: the prompt, the text in full colour, then
     * the dim ghost, with the cursor where it is in the text. On the pair
     * screen the line takes as many rows as it lays out onto, the row being
     * typed on kept in view; without one it is repainted in place.
     */
    private function render(string $prompt, TypedLine $line, ?string $ghost): void
    {
        $input = $this->wrapped($prompt, $line, $ghost);
        $area = $this->output->inputArea($input->rowCount());

        $this->output->rawOutput()->write($area === null ? $input->inPlace() : $input->paintedIn($area), false, OutputInterface::OUTPUT_RAW);
    }

    private function wrapped(string $prompt, TypedLine $line, ?string $ghost): WrappedInput
    {
        return new WrappedInput($prompt, $line->text, $ghost, $this->output->width(), $line->cursor);
    }

    /**
     * Reads one whole line from stdin, or null on EOF.
     */
    private function readStdinLine(): ?string
    {
        $line = fgets(STDIN);

        return $line === false ? null : rtrim($line, "\r\n");
    }
}
