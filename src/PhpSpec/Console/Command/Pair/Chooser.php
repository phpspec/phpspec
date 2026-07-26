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
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * @internal
 * Presents interactive questions as a numbered chooser, in the style of
 * AI CLI assistants:
 *
 *   ▸ 1. Yes
 *     2. Yes, and don't ask again — always <action>
 *     3. No
 *
 * On a TTY a single keypress decides — 1/2/3, y (yes), a (always), n or
 * Esc (no), no Enter needed — and the arrow keys move the highlight with
 * Enter confirming it. Piped input falls back to reading one answer line
 * (digits or y/a/n; empty defaults to yes) so scripted sessions keep
 * working. Option 2 is remembered per question kind for the session, so
 * later questions of the same kind auto-accept without prompting.
 * Non-interactive sessions print the question and auto-accept.
 */
final class Chooser
{
    /** @var array<string, true> question kinds answered with "always" this session */
    private array $always = [];

    /**
     * The note typed alongside the latest answer (Tab on Yes/No opens an
     * editable trailer; line mode reads it after a comma), empty when none.
     */
    private string $note = '';

    private readonly Closure $reader;

    private readonly ?Closure $keys;

    /**
     * @param PairOutput $output the pair screen to render into
     * @param bool $interactive whether a user is attached; false auto-accepts
     * @param Closure|null $reader reads one answer line (piped input); injectable
     *                             for specs, defaults to the stdin flow
     * @param Closure|null $keys reads one raw key token (TTY input); injectable
     *                           for specs — when given, key mode is used without
     *                           touching the real terminal
     */
    private readonly bool $detectTty;

    public function __construct(
        private readonly PairOutput $output,
        private readonly bool $interactive = true,
        ?Closure $reader = null,
        ?Closure $keys = null,
    ) {
        $this->reader = $reader ?? fn(): string => $this->readAnswer();
        $this->keys = $keys;
        // Only probe the real terminal when no input source was injected,
        // so specs behave the same whether run from a TTY or a pipe
        $this->detectTty = $reader === null && $keys === null;
    }

    /**
     * Asks a yes/always/no question and returns whether the action was accepted.
     *
     * @param string $question the question to display
     * @param string $kind stable identifier grouping questions for "always" memory
     * @param string $action verb phrase completing "always ..." in option 2
     * @return bool true when the user accepted (or accepted always, now or earlier)
     */
    public function choose(string $question, string $kind, string $action): bool
    {
        $this->note = '';

        if (isset($this->always[$kind])) {
            return true;
        }

        $console = $this->output->getOutput();
        $console->writeln('');
        $console->writeln(sprintf('  <fg=yellow>%s</>', $question));

        if (!$this->interactive) {
            return true;
        }

        // Raw key mode needs stty, so Windows terminals use line mode
        $keyCapable = DIRECTORY_SEPARATOR === '/' && $this->detectTty && stream_isatty(STDIN);

        if ($this->keys !== null || $keyCapable) {
            return $this->resolve($this->chooseByKey($action), $kind);
        }

        return $this->resolve($this->chooseByLine($action), $kind);
    }

    /**
     * Returns whether a question kind was answered with "always" this session.
     *
     * @param string $kind the question kind identifier
     * @return bool true when future questions of this kind auto-accept
     */
    public function hasAlways(string $kind): bool
    {
        return isset($this->always[$kind]);
    }

    /**
     * The note the human typed alongside the latest answer: Tab on Yes or No
     * opens an editable trailer ("1. Yes, rename it"), and a piped answer line
     * carries it after a comma ("3, make it a Feature instead"). Empty when the
     * answer came without one.
     */
    public function lastNote(): string
    {
        return $this->note;
    }

    /**
     * Applies the chosen option: records "always", reports acceptance.
     *
     * @param int $choice the selected option (1 yes, 2 always, 3 no)
     * @param string $kind the question kind for "always" memory
     * @return bool true when option 1 or 2 was chosen
     */
    private function resolve(int $choice, string $kind): bool
    {
        if ($choice === 2) {
            $this->always[$kind] = true;
        }

        return $choice !== 3;
    }

    /**
     * Key mode (TTY): a single keypress decides, the arrow keys move the
     * highlight and Enter confirms it. Tab on Yes or No opens an editable note
     * trailer on the highlighted line (Enter confirms answer and note together,
     * Esc discards the note, backspace edits it).
     *
     * @param string $action verb phrase for option 2
     * @return int the selected option (1, 2 or 3)
     */
    private function chooseByKey(string $action): int
    {
        $selected = 1;
        $rendered = false;
        $note = null;
        $rawMode = $this->keys === null;

        if ($rawMode) {
            system('stty -icanon -echo');
        }

        try {
            $this->renderOptions($selected, $action, $rendered);

            while (true) {
                $key = $this->keys !== null ? ($this->keys)() : $this->readKeyToken();

                if ($note !== null) {
                    switch ($key) {
                        case "\n":
                        case "\r":
                            $this->note = trim($note);

                            return $selected;
                        case "\033":
                            $note = null;
                            break;
                        case "\x7f":
                        case "\x08":
                            $note = substr($note, 0, -1);
                            break;
                        default:
                            if (strlen($key) === 1 && $key >= ' ') {
                                $note .= $key;
                            }
                    }

                    $this->renderOptions($selected, $action, $rendered, $note);
                    continue;
                }

                switch ($key) {
                    case "\033[A":
                        $selected = max(1, $selected - 1);
                        break;
                    case "\033[B":
                        $selected = min(3, $selected + 1);
                        break;
                    case "\t":
                        if ($selected !== 2) {
                            $note = '';
                        }
                        break;
                    case "\n":
                    case "\r":
                        return $selected;
                    case '1':
                    case 'y':
                    case 'Y':
                        $this->renderOptions(1, $action, $rendered);

                        return 1;
                    case '2':
                    case 'a':
                    case 'A':
                        $this->renderOptions(2, $action, $rendered);

                        return 2;
                    case '3':
                    case 'n':
                    case 'N':
                    case "\033":
                        $this->renderOptions(3, $action, $rendered);

                        return 3;
                    default:
                        continue 2;
                }

                $this->renderOptions($selected, $action, $rendered, $note);
            }
        } finally {
            if ($rawMode) {
                system('stty icanon echo');
            }
        }
    }

    /**
     * Line mode (piped input): prints the options and reads one answer line.
     * Anything after a comma is kept as the answer's note ("3, make it a
     * Feature instead").
     *
     * @param string $action verb phrase for option 2
     * @return int the selected option (1, 2 or 3)
     */
    private function chooseByLine(string $action): int
    {
        $rendered = false;
        $this->renderOptions(1, $action, $rendered);

        $answer = trim(($this->reader)());
        if (str_contains($answer, ',')) {
            [$answer, $note] = explode(',', $answer, 2);
            $this->note = trim($note);
        }

        return match (strtolower(trim($answer))) {
            '2', 'a' => 2,
            '3', 'n' => 3,
            default => 1,
        };
    }

    /**
     * Renders the three options with the current highlight, redrawing in
     * place after the first paint. A non-null note renders as an editable
     * trailer on the highlighted line ("Yes, rename it▮").
     *
     * @param int $selected the highlighted option
     * @param string $action verb phrase for option 2
     * @param bool $rendered whether a previous paint must be overwritten (by reference)
     * @param string|null $note the note being typed, null when not in note mode
     */
    private function renderOptions(int $selected, string $action, bool &$rendered, ?string $note = null): void
    {
        $console = $this->output->getOutput();

        if ($rendered) {
            $console->write("\033[3A");
        }

        $rendered = true;

        $labels = [
            1 => 'Yes',
            2 => "Yes, and don't ask again — always $action",
            3 => 'No',
        ];

        if ($note !== null && $selected !== 2) {
            $labels[$selected] .= ', ' . OutputFormatter::escape($note) . '▮';
        }

        foreach ($labels as $number => $label) {
            if ($number === $selected) {
                $console->writeln(sprintf("  <options=reverse> ▸ %d. %s </>\033[K", $number, $label));
            } else {
                $console->writeln(sprintf("     <fg=white>%d.</> %s\033[K", $number, $label));
            }
        }
    }

    /**
     * Reads one raw key token from the terminal: a single character, or a
     * complete escape sequence for the arrow keys (bare Esc stays a lone \033).
     *
     * @return string the key token
     */
    private function readKeyToken(): string
    {
        $char = fgetc(STDIN);

        if ($char === false) {
            return "\n";
        }

        if ($char !== "\033") {
            return $char;
        }

        $read = [STDIN];
        $write = $except = [];

        if ((int) stream_select($read, $write, $except, 0, 20000) < 1) {
            return "\033";
        }

        $bracket = fgetc(STDIN);

        if ($bracket !== '[') {
            return "\033";
        }

        return "\033[" . fgetc(STDIN);
    }

    /**
     * Reads one answer line from piped stdin, cooperating with the pair
     * screen's reserved input row.
     *
     * @return string the raw answer
     */
    private function readAnswer(): string
    {
        $this->output->prepareForInput();
        $result = trim((string) fgets(STDIN));
        $this->output->returnToContent();
        $this->output->echoInput($result !== '' ? $result : '1');

        return $result;
    }
}
