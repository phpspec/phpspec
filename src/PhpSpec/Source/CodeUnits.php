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
 * The units of code a source file compiles to, read from its tokens: each
 * function, method, closure, arrow function and property hook is a unit of
 * its own, and the file's own code, outside them all, is one more. A line
 * holds code of every unit with a token on it.
 */
final class CodeUnits
{
    public const FILE = 0;

    private const OPENS = [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES, 123];
    private const TYPES = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE, T_ARRAY, T_CALLABLE, T_NS_SEPARATOR];

    /** @var list<PhpToken> */
    private readonly array $tokens;

    /** @var array<int, array<int, true>> line => the units holding code on it */
    private array $owners = [];

    public function __construct(string $source)
    {
        $this->tokens = array_values(PhpToken::tokenize($source));
        $this->own($this->units());
    }

    /**
     * The one unit holding code on the line, or null when the line holds code
     * of several, or none.
     */
    public function soleOwner(int $line): ?int
    {
        $owners = $this->owners[$line] ?? [];

        return count($owners) === 1 ? array_key_first($owners) : null;
    }

    /**
     * Where each unit begins and ends, as token positions, in the order they
     * begin.
     *
     * @return list<array{int, int}>
     */
    private function units(): array
    {
        $units = [];

        foreach ($this->tokens as $at => $token) {
            if ($token->is(T_FUNCTION) && !$this->importedAt($at)) {
                $open = $this->bodyOpen($at);

                if ($this->tokens[$open]->text !== ';') {
                    $units[] = [$at, $this->matchingClose($open)];
                }
            } elseif ($token->is(T_FN)) {
                $units[] = [$at, $this->arrowEnd($at)];
            } elseif ($token->is(T_VARIABLE) && $this->declaresAPropertyAt($at)) {
                array_push($units, ...$this->hooksOf($at));
            }
        }

        usort($units, static fn(array $one, array $other): int => $one[0] <=> $other[0]);

        return $units;
    }

    /**
     * Gives each line the units with a token on it, a token belonging to the
     * innermost unit around it.
     *
     * @param list<array{int, int}> $units
     */
    private function own(array $units): void
    {
        $open = [];
        $next = 0;

        foreach ($this->tokens as $at => $token) {
            while ($open !== [] && $units[$open[array_key_last($open)]][1] < $at) {
                array_pop($open);
            }

            while ($next < count($units) && $units[$next][0] === $at) {
                $open[] = $next++;
            }

            if ($token->isIgnorable()) {
                continue;
            }

            $unit = $open === [] ? self::FILE : $open[array_key_last($open)] + 1;

            for ($line = $token->line; $line <= $token->line + substr_count($token->text, "\n"); $line++) {
                $this->owners[$line][$unit] = true;
            }
        }
    }

    private function importedAt(int $at): bool
    {
        $before = $this->significantBefore($at);

        return $before !== null && $this->tokens[$before]->is(T_USE);
    }

    /**
     * Whether the variable names a property: a modifier comes before it and
     * its type, as in a class body or a promoted constructor parameter.
     */
    private function declaresAPropertyAt(int $at): bool
    {
        for ($i = $this->significantBefore($at); $i !== null; $i = $this->significantBefore($i)) {
            $token = $this->tokens[$i];

            if ($token->is(self::TYPES) || in_array($token->text, ['?', '|', '&', '(', ')'], true)) {
                continue;
            }

            return $token->is(self::modifiers());
        }

        return false;
    }

    /**
     * The hooks of the property declared at the variable, each from its name
     * to the end of its body.
     *
     * @return list<array{int, int}>
     */
    private function hooksOf(int $at): array
    {
        $list = $this->hookListAfter($at);

        if ($list === null) {
            return [];
        }

        $hooks = [];
        $close = $this->matchingClose($list);

        for ($i = $list + 1; $i < $close; $i++) {
            $token = $this->tokens[$i];

            if ($token->is(T_ATTRIBUTE)) {
                $i = $this->matchingBracket($i);
            } elseif ($token->is(T_STRING) && in_array(strtolower($token->text), ['get', 'set'], true)) {
                $end = $this->hookEnd($i);
                $hooks[] = [$i, $end];
                $i = $end;
            }
        }

        return $hooks;
    }

    /**
     * Where the property's hook list opens, past any default value, or null
     * when the declaration ends without one.
     */
    private function hookListAfter(int $at): ?int
    {
        $nesting = 0;

        for ($i = $at + 1; $i < count($this->tokens); $i++) {
            $token = $this->tokens[$i];

            if ($token->is(T_FUNCTION)) {
                $i = $this->matchingClose($this->bodyOpen($i));

                continue;
            }

            if ($nesting === 0 && $token->id === 123) {
                return $i;
            }

            $nesting += match ($token->text) {
                '(', '[' => 1,
                ')', ']' => -1,
                default => 0,
            };

            if ($nesting < 0 || ($nesting === 0 && in_array($token->text, [';', ','], true))) {
                return null;
            }
        }

        return null;
    }

    /**
     * The last token of a hook: the brace closing its body, or the semicolon
     * ending its expression.
     */
    private function hookEnd(int $name): int
    {
        $next = $this->significantAfter($name);

        if ($next !== null && $this->tokens[$next]->text === '(') {
            $next = $this->significantAfter($this->matchingBracket($next));
        }

        if ($next === null) {
            return count($this->tokens) - 1;
        }

        if ($this->tokens[$next]->id === 123) {
            return $this->matchingClose($next);
        }

        $nesting = 0;

        for ($i = $next; $i < count($this->tokens); $i++) {
            $text = $this->tokens[$i]->text;
            $nesting += match ($text) {
                '(', '[', '{' => 1,
                ')', ']', '}' => -1,
                default => 0,
            };

            if ($nesting < 0 || ($nesting === 0 && $text === ';')) {
                return $nesting < 0 ? $i - 1 : $i;
            }
        }

        return count($this->tokens) - 1;
    }

    /**
     * Where the body after a declaration opens, or the semicolon a method
     * without one ends on.
     */
    private function bodyOpen(int $from): int
    {
        $nesting = 0;

        for ($i = $from + 1; $i < count($this->tokens); $i++) {
            $text = $this->tokens[$i]->text;
            $nesting += match ($text) {
                '(', '[' => 1,
                ')', ']' => -1,
                default => 0,
            };

            if ($nesting === 0 && ($this->tokens[$i]->id === 123 || $text === ';')) {
                return $i;
            }
        }

        return count($this->tokens) - 1;
    }

    /**
     * The last token of an arrow function's expression: before the separator
     * or the bracket that ends it.
     */
    private function arrowEnd(int $from): int
    {
        $nesting = 0;

        for ($i = $from + 1; $i < count($this->tokens); $i++) {
            $text = $this->tokens[$i]->text;
            $nesting += match ($text) {
                '(', '[', '{' => 1,
                ')', ']', '}' => -1,
                default => 0,
            };

            if ($nesting < 0 || ($nesting === 0 && in_array($text, [';', ','], true))) {
                return $i - 1;
            }
        }

        return count($this->tokens) - 1;
    }

    private function matchingClose(int $open): int
    {
        $depth = 0;

        for ($i = $open; $i < count($this->tokens); $i++) {
            if ($this->tokens[$i]->is(self::OPENS)) {
                $depth++;
            } elseif ($this->tokens[$i]->text === '}' && --$depth === 0) {
                return $i;
            }
        }

        return count($this->tokens) - 1;
    }

    /**
     * The bracket or parenthesis closing the one the token opens.
     */
    private function matchingBracket(int $open): int
    {
        $depth = 0;

        for ($i = $open; $i < count($this->tokens); $i++) {
            $text = $this->tokens[$i]->text;
            $depth += match ($text) {
                '(', '[', '#[' => 1,
                ')', ']' => -1,
                default => 0,
            };

            if ($depth === 0) {
                return $i;
            }
        }

        return count($this->tokens) - 1;
    }

    private function significantBefore(int $at): ?int
    {
        for ($i = $at - 1; $i >= 0; $i--) {
            if (!$this->tokens[$i]->isIgnorable()) {
                return $i;
            }
        }

        return null;
    }

    private function significantAfter(int $at): ?int
    {
        for ($i = $at + 1; $i < count($this->tokens); $i++) {
            if (!$this->tokens[$i]->isIgnorable()) {
                return $i;
            }
        }

        return null;
    }

    /**
     * The keywords that declare a property, the asymmetric visibilities of
     * PHP 8.4 among them where PHP has them.
     *
     * @return list<int>
     */
    private static function modifiers(): array
    {
        $modifiers = [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_VAR, T_READONLY, T_STATIC, T_FINAL, T_ABSTRACT];

        foreach (['T_PUBLIC_SET', 'T_PROTECTED_SET', 'T_PRIVATE_SET'] as $name) {
            if (defined($name)) {
                $modifiers[] = (int) constant($name);
            }
        }

        return $modifiers;
    }
}
