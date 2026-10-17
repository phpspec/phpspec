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
 * Reads the keys a terminal sends, one whole key at a time: a character
 * (all of its UTF-8 bytes), an escape sequence with all of its parameters,
 * Esc and the key after it as Meta sends them, Esc alone when nothing follows
 * it, and a bracketed paste from its opening marker to its closing one.
 */
final class TerminalKeys
{
    private const PASTE_START = "\033[200~";
    private const PASTE_END = "\033[201~";

    /** @var resource */
    private $stream;

    /**
     * @param resource|null $stream where the keys come from, the terminal by default
     */
    public function __construct($stream = null)
    {
        $this->stream = $stream ?? STDIN;
    }

    public function next(): string
    {
        $first = fgetc($this->stream);

        if ($first === false) {
            return "\004";
        }

        if ($first !== "\033") {
            return $first . $this->restOfCharacter($first);
        }

        if (!$this->more()) {
            return "\033";
        }

        $second = fgetc($this->stream);

        return match ($second) {
            false => "\033",
            '[' => $this->controlSequence(),
            'O' => "\033O" . fgetc($this->stream),
            default => "\033" . $second . $this->restOfCharacter($second),
        };
    }

    /**
     * The rest of a sequence that began with Esc [: parameters up to the
     * final byte, and the whole of a paste when the sequence opened one.
     */
    private function controlSequence(): string
    {
        $sequence = "\033[";

        do {
            $byte = fgetc($this->stream);

            if ($byte === false) {
                return $sequence;
            }

            $sequence .= $byte;
        } while (ord($byte) < 0x40 || ord($byte) > 0x7E);

        if ($sequence !== self::PASTE_START) {
            return $sequence;
        }

        while (!str_ends_with($sequence, self::PASTE_END)) {
            $byte = fgetc($this->stream);

            if ($byte === false) {
                break;
            }

            $sequence .= $byte;
        }

        return $sequence;
    }

    /**
     * The bytes that complete a UTF-8 character after its first.
     */
    private function restOfCharacter(string $first): string
    {
        $lead = ord($first);
        $following = match (true) {
            $lead >= 0xF0 => 3,
            $lead >= 0xE0 => 2,
            $lead >= 0xC0 => 1,
            default => 0,
        };

        return $following === 0 ? '' : (string) fread($this->stream, $following);
    }

    /**
     * Whether another byte arrives right after Esc, as it does in a sequence,
     * rather than Esc having been pressed alone.
     */
    private function more(): bool
    {
        $read = [$this->stream];
        $write = $except = [];

        return (int) stream_select($read, $write, $except, 0, 20000) > 0;
    }
}
