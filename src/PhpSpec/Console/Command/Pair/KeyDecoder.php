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
 * Reads what a terminal sends for a key: the legacy bytes, the xterm
 * sequences for modified keys, and the CSI u codes a terminal reports keys
 * with once asked to tell them apart (so Shift+Enter is not Enter).
 */
final class KeyDecoder
{
    private const SHIFT = 1;
    private const ALT = 2;
    private const CTRL = 4;
    private const META = 8;

    private const LEGACY = [
        "\r" => KeyName::Enter,
        "\n" => KeyName::Enter,
        "\033\r" => KeyName::NewLine,
        "\033\n" => KeyName::NewLine,
        "\033b" => KeyName::WordLeft,
        "\033f" => KeyName::WordRight,
        "\033[1~" => KeyName::Home,
        "\033[7~" => KeyName::Home,
        "\001" => KeyName::Home,
        "\033[4~" => KeyName::End,
        "\033[8~" => KeyName::End,
        "\005" => KeyName::End,
        "\177" => KeyName::Backspace,
        "\010" => KeyName::Backspace,
        "\033[3~" => KeyName::Delete,
        "\t" => KeyName::Tab,
        "\033" => KeyName::Cancel,
        "\003" => KeyName::Cancel,
        "\004" => KeyName::EndOfInput,
    ];

    private const ARROWS = ['A' => KeyName::Up, 'B' => KeyName::Down, 'C' => KeyName::Right, 'D' => KeyName::Left, 'H' => KeyName::Home, 'F' => KeyName::End];

    public function decode(string $token): Key
    {
        if (isset(self::LEGACY[$token])) {
            return new Key(self::LEGACY[$token]);
        }

        if (str_starts_with($token, "\033[200~")) {
            return new Key(KeyName::Text, $this->pasted($token));
        }

        if (preg_match('/^\033[\[O](?:1;(\d+))?([ABCDHF])$/', $token, $arrow) === 1) {
            return new Key($this->arrow($arrow[2], (int) ($arrow[1] ?: 1) - 1));
        }

        if (preg_match('/^\033\[(\d+)(?::\d+)*(?:;(\d+)(?::\d+)?)?u$/', $token, $code) === 1) {
            return $this->coded((int) $code[1], (int) ($code[2] ?? 1) - 1);
        }

        if (preg_match('/^\033\[27;(\d+);(\d+)~$/', $token, $code) === 1) {
            return $this->coded((int) $code[2], (int) $code[1] - 1);
        }

        if ($token !== '' && $token[0] !== "\033" && ord($token[0]) >= 32 && mb_check_encoding($token, 'UTF-8')) {
            return new Key(KeyName::Text, $token);
        }

        return new Key(KeyName::Ignored);
    }

    /**
     * An arrow, Home or End, a modifier on a sideways arrow making it a move
     * by a word.
     */
    private function arrow(string $final, int $modifiers): KeyName
    {
        $wordwise = ($modifiers & (self::ALT | self::CTRL | self::META)) !== 0;

        return match (true) {
            $wordwise && $final === 'D' => KeyName::WordLeft,
            $wordwise && $final === 'C' => KeyName::WordRight,
            default => self::ARROWS[$final],
        };
    }

    /**
     * A key reported by its code point and its modifiers.
     */
    private function coded(int $codePoint, int $modifiers): Key
    {
        $ctrl = ($modifiers & self::CTRL) !== 0;
        $alt = ($modifiers & (self::ALT | self::META)) !== 0;

        $name = match (true) {
            $codePoint === 13 => ($modifiers & (self::SHIFT | self::ALT | self::META)) !== 0 ? KeyName::NewLine : KeyName::Enter,
            $codePoint === 27 => KeyName::Cancel,
            $codePoint === 9 => KeyName::Tab,
            $codePoint === 127 => KeyName::Backspace,
            $ctrl && $codePoint === 99 => KeyName::Cancel,
            $ctrl && $codePoint === 100 => KeyName::EndOfInput,
            $ctrl && $codePoint === 97 => KeyName::Home,
            $ctrl && $codePoint === 101 => KeyName::End,
            $alt && $codePoint === 98 => KeyName::WordLeft,
            $alt && $codePoint === 102 => KeyName::WordRight,
            !$ctrl && !$alt && $codePoint >= 32 => KeyName::Text,
            default => KeyName::Ignored,
        };

        if ($name !== KeyName::Text) {
            return new Key($name);
        }

        $character = mb_chr($codePoint, 'UTF-8');

        return new Key(KeyName::Text, ($modifiers & self::SHIFT) !== 0 ? mb_strtoupper((string) $character) : (string) $character);
    }

    /**
     * What was pasted, its line breaks made one kind and its tabs spaces, so
     * it lays out as it reads.
     */
    private function pasted(string $token): string
    {
        $text = substr($token, strlen("\033[200~"));
        $text = str_ends_with($text, "\033[201~") ? substr($text, 0, -strlen("\033[201~")) : $text;

        return str_replace(["\r\n", "\r", "\t"], ["\n", "\n", '    '], $text);
    }
}
