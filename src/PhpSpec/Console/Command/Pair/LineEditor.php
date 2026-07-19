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
 * A raw-mode line editor for the pair REPL. Beyond reading a line, it can seed
 * the input with a dim "ghost" suggestion (the next command) that the user
 * accepts with Right-arrow or Tab — fish-shell style. Typing dismisses it.
 *
 * On a TTY a byte-by-byte key loop drives it (stty raw); piped input falls back
 * to reading a whole line so scripted sessions keep working. Both the key reader
 * and the line reader are injectable, so specs drive it without a real terminal.
 */
final class LineEditor
{
    private const DIM = "\033[2m";
    private const RESET = "\033[0m";

    private readonly Closure $reader;

    private readonly ?Closure $keys;

    private readonly bool $detectTty;

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
        // Only probe the real terminal when no input source was injected, so
        // specs behave the same whether run from a TTY or a pipe.
        $this->detectTty = $reader === null && $keys === null;
    }

    /**
     * Reads one line, optionally seeded with a dim suggestion the user accepts
     * with Right-arrow or Tab. Up/Down walk the given history. Returns the line,
     * or null on EOF / cancel.
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
     * Key mode (TTY): a per-keystroke loop that maintains the buffer, paints the
     * ghost after it, accepts it on Right-arrow / Tab, and walks history on Up/Down.
     *
     * @param list<string> $history earlier lines, oldest first
     */
    private function readByKey(string $prompt, ?string $suggestion, array $history): ?string
    {
        $rawMode = $this->keys === null;
        if ($rawMode) {
            system('stty -icanon -echo');
        }

        $buffer = '';
        $ghost = ($suggestion !== null && $suggestion !== '') ? $suggestion : null;
        $histAt = count($history);

        try {
            $this->render($prompt, $buffer, $ghost);

            while (true) {
                $key = $this->keys !== null ? ($this->keys)() : $this->readKeyToken();

                if ($key === "\n" || $key === "\r") {
                    return $buffer;
                }

                if ($key === "\004") {
                    return $buffer === '' ? null : $buffer;
                }

                if ($key === "\003" || $key === "\033") {
                    return null;
                }

                if (($key === "\033[C" || $key === "\t") && $ghost !== null && $buffer === '') {
                    $buffer = $ghost;
                    $ghost = null;
                } elseif ($key === "\033[A" && $histAt > 0) {
                    $buffer = $history[--$histAt];
                    $ghost = null;
                } elseif ($key === "\033[B" && $histAt < count($history)) {
                    $buffer = ++$histAt < count($history) ? $history[$histAt] : '';
                    $ghost = null;
                } elseif ($key === "\177" || $key === "\010") {
                    $buffer = mb_substr($buffer, 0, -1);
                } elseif (strlen($key) === 1 && ord($key) >= 32) {
                    $buffer .= $key;
                    $ghost = null;
                } else {
                    continue;
                }

                $this->render($prompt, $buffer, $ghost);
            }
        } finally {
            if ($rawMode) {
                system('stty icanon echo');
            }
        }
    }

    /**
     * Repaints the input row in place: the prompt, the buffer in full colour,
     * then the dim ghost — with the cursor moved back to sit before the ghost.
     */
    private function render(string $prompt, string $buffer, ?string $ghost): void
    {
        $line = "\r\033[K" . $prompt . $buffer;

        if ($ghost !== null && $ghost !== '') {
            $line .= self::DIM . $ghost . self::RESET . "\033[" . mb_strlen($ghost) . 'D';
        }

        $this->output->rawOutput()->write($line, false, OutputInterface::OUTPUT_RAW);
    }

    /**
     * Reads one raw key token: a single character, or a complete escape sequence
     * for the arrow keys (bare Esc stays a lone \033; EOF becomes Ctrl-D).
     */
    private function readKeyToken(): string
    {
        $char = fgetc(STDIN);

        if ($char === false) {
            return "\004";
        }

        if ($char !== "\033") {
            return $char;
        }

        $read = [STDIN];
        $write = $except = [];

        if ((int) stream_select($read, $write, $except, 0, 20000) < 1) {
            return "\033";
        }

        if (fgetc(STDIN) !== '[') {
            return "\033";
        }

        return "\033[" . fgetc(STDIN);
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
