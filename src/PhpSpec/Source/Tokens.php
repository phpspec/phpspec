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

namespace PhpSpec\Source;

use PhpToken;

/**
 * @internal
 * A source file's tokens, and the ways the readers find their way through
 * them: where a body opens and closes, where an arrow function's expression
 * ends, and the tokens either side that are not whitespace or comments.
 */
final readonly class Tokens
{
    /** the tokens that open a brace a closing brace ends */
    public const OPENS = [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES, 123];

    /**
     * @param list<PhpToken> $list
     */
    public function __construct(public array $list) {}

    public static function of(string $source): self
    {
        return new self(array_values(PhpToken::tokenize($source)));
    }

    /**
     * Where the body after a declaration opens, or the semicolon a method
     * without one ends on.
     */
    public function bodyOpen(int $from): int
    {
        $nesting = 0;

        for ($i = $from + 1; $i < count($this->list); $i++) {
            $text = $this->list[$i]->text;
            $nesting += match ($text) {
                '(', '[' => 1,
                ')', ']' => -1,
                default => 0,
            };

            if ($nesting === 0 && ($this->list[$i]->id === 123 || $text === ';')) {
                return $i;
            }
        }

        return count($this->list) - 1;
    }

    /**
     * The last token of an arrow function's expression: before the separator
     * or the bracket that ends it.
     */
    public function arrowEnd(int $from): int
    {
        $nesting = 0;

        for ($i = $from + 1; $i < count($this->list); $i++) {
            $text = $this->list[$i]->text;
            $nesting += match ($text) {
                '(', '[', '{' => 1,
                ')', ']', '}' => -1,
                default => 0,
            };

            if ($nesting < 0 || ($nesting === 0 && in_array($text, [';', ','], true))) {
                return $i - 1;
            }
        }

        return count($this->list) - 1;
    }

    public function matchingClose(int $open): int
    {
        $depth = 0;

        for ($i = $open; $i < count($this->list); $i++) {
            if ($this->list[$i]->is(self::OPENS)) {
                $depth++;
            } elseif ($this->list[$i]->text === '}' && --$depth === 0) {
                return $i;
            }
        }

        return count($this->list) - 1;
    }

    public function significantBefore(int $at): ?int
    {
        for ($i = $at - 1; $i >= 0; $i--) {
            if (!$this->list[$i]->isIgnorable()) {
                return $i;
            }
        }

        return null;
    }

    public function significantAfter(int $at): ?int
    {
        for ($i = $at + 1; $i < count($this->list); $i++) {
            if (!$this->list[$i]->isIgnorable()) {
                return $i;
            }
        }

        return null;
    }
}
