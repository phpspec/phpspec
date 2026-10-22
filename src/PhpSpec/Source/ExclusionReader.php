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

use Closure;
use PhpToken;

/**
 * @internal
 * Reads a source file's tokens for what coverage leaves out. Declarations are
 * found by walking the tokens: a class, interface, trait or enum is followed
 * into its body for its methods, a function's body is stepped over whole. A
 * comment tag is then resolved against the statement it tags. The lines that
 * only declare, which an engine reports as run whenever the file loads, are
 * left out too: a class header, a namespaced constant, the end of the file.
 */
final class ExclusionReader
{
    private const ATTRIBUTE = 'excludefromcoverage';
    private const TAG = '/@codeCoverageIgnore(?!Start|End)\b/';
    private const KINDS = [T_CLASS => 'class', T_TRAIT => 'class', T_ENUM => 'class', T_INTERFACE => 'interface'];
    private const MODIFIERS = [T_ABSTRACT, T_FINAL, T_READONLY, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_VAR];
    private const OPENS = [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES, 123];

    /** @var list<PhpToken> */
    private readonly array $tokens;

    /** @var array<int, true> */
    private array $excluded = [];

    /** @var array<int, true> the docblocks a declaration took, which tag no line */
    private array $attached = [];

    /** @var array<int, true> the tokens that close a function's body */
    private array $functionEnds = [];

    private int $declared = 0;

    private int $declaredExcluded = 0;

    private bool $ownCode = false;

    /**
     * @param Closure(string, string): bool $named
     */
    public function __construct(string $source, private readonly Closure $named)
    {
        $this->tokens = array_values(PhpToken::tokenize($source));
    }

    public function read(): ExcludedLines
    {
        $this->readDeclarations();
        $this->readEnd();
        $this->readTags();
        $lines = array_keys($this->excluded);
        sort($lines);

        return new ExcludedLines($lines, $this->declared > 0 && $this->declared === $this->declaredExcluded && !$this->ownCode);
    }

    private function readDeclarations(): void
    {
        $namespace = '';
        $classes = [];
        $marked = false;
        $doc = null;

        for ($i = 0; $i < count($this->tokens); $i++) {
            $token = $this->tokens[$i];

            if ($token->isIgnorable() || $token->is([T_OPEN_TAG, T_CLOSE_TAG, T_INLINE_HTML]) || $token->is(self::MODIFIERS)) {
                continue;
            }

            if ($token->is(T_DOC_COMMENT)) {
                $doc = $i;

                continue;
            }

            if ($token->is(T_ATTRIBUTE)) {
                [$i, $named] = $this->attributeGroup($i);
                $marked = $marked || $named;

                continue;
            }

            $class = $classes === [] ? null : $classes[array_key_last($classes)];

            if ($class !== null && $i === $class['end']) {
                array_pop($classes);
            } elseif ($token->is(T_NAMESPACE)) {
                [$i, $namespace] = $this->namespaceAt($i);
            } elseif ($token->is([T_USE, T_DECLARE])) {
                $i = $this->statementEnd($i);
            } elseif ($token->is(T_CONST) && $class === null) {
                $end = $this->statementEnd($i);
                $this->exclude($token->line, $this->tokens[$end]->line);
                $i = $end;
            } elseif ($token->is(array_keys(self::KINDS)) && $this->declaresAt($i)) {
                $classes[] = $this->classLike($i, $namespace, $marked, $doc, $class === null);
                $i = $this->bodyOpen($i);
            } elseif ($token->is(T_CLASS) && !$this->namesAClassAt($i)) {
                $i = $this->matchingClose($this->bodyOpen($i));
            } elseif ($token->is(T_FUNCTION) && $this->nameAfter($i) !== null) {
                $i = $this->function($i, $class === null ? $namespace : $class['name'] . '::', $marked, $doc, $class === null);
            } elseif ($class === null && !$token->is([123, ord('}'), ord(';')])) {
                $this->ownCode = true;
            }

            $marked = false;
            $doc = null;
        }
    }

    /**
     * A class, interface, trait or enum: its lines when it is left out, and
     * where its body closes, so its methods are read as its own.
     *
     * @return array{name: string, end: int}
     */
    private function classLike(int $at, string $namespace, bool $marked, ?int $doc, bool $topLevel): array
    {
        $kind = self::KINDS[$this->tokens[$at]->id];
        $name = $namespace . $this->nameAfter($at);
        $open = $this->bodyOpen($at);
        $end = $this->matchingClose($open);
        $this->exclude($this->tokens[$at]->line, $this->tokens[$open]->line);
        $excluded = $kind === 'interface' || $marked || $this->tagged($doc) || ($this->named)('class', $name);

        $this->declare($at, $end, $excluded, $doc, $topLevel);

        return ['name' => $name, 'end' => $end];
    }

    /**
     * A method or function: its lines when it is left out. Its body is
     * stepped over whole, closures and anonymous classes with it.
     */
    private function function(int $at, string $owner, bool $marked, ?int $doc, bool $topLevel): int
    {
        $name = $owner . $this->nameAfter($at);
        $open = $this->bodyOpen($at);
        $end = $this->tokens[$open]->text === ';' ? $open : $this->matchingClose($open);
        $excluded = $marked || $this->tagged($doc) || ($this->named)($topLevel ? 'function' : 'method', $name);
        $this->functionEnds[$end] = true;

        $this->declare($at, $end, $excluded, $doc, $topLevel);

        return $end;
    }

    private function declare(int $at, int $end, bool $excluded, ?int $doc, bool $topLevel): void
    {
        if ($doc !== null) {
            $this->attached[$doc] = true;
        }

        if ($topLevel) {
            $this->declared++;
            $this->declaredExcluded += $excluded ? 1 : 0;
        }

        if ($excluded) {
            $this->exclude($this->tokens[$at]->line, $this->tokens[$end]->line);
        }
    }

    /**
     * The line the engine closes the file on: the one after the last when a
     * newline ends the file, else the last, unless a function or a statement
     * ends there.
     */
    private function readEnd(): void
    {
        $lastIndex = array_key_last($this->tokens);

        if ($lastIndex === null) {
            return;
        }

        $last = $this->tokens[$lastIndex];
        $line = $last->line + substr_count($last->text, "\n");
        $closing = $this->tokens[$lastIndex]->isIgnorable() ? $this->significantBefore($lastIndex) : $lastIndex;

        if ($closing !== null && $this->tokens[$closing]->line === $line && ($this->tokens[$closing]->text !== '}' || isset($this->functionEnds[$closing]))) {
            return;
        }

        $this->exclude($line, $line);
    }

    /**
     * Resolves every @codeCoverageIgnore comment, and every stretch between
     * a Start and an End; a Start never ended runs to the end of the file.
     */
    private function readTags(): void
    {
        $start = null;

        foreach ($this->tokens as $i => $token) {
            if (!$token->is(T_COMMENT) && !($token->is(T_DOC_COMMENT) && !isset($this->attached[$i]))) {
                continue;
            }

            if (str_contains($token->text, '@codeCoverageIgnoreStart')) {
                $start ??= $token->line;
            } elseif (str_contains($token->text, '@codeCoverageIgnoreEnd') && $start !== null) {
                $this->exclude($start, $token->line);
                $start = null;
            } elseif (preg_match(self::TAG, $token->text) === 1) {
                $this->tag($i);
            }
        }

        if ($start !== null) {
            $last = $this->tokens[array_key_last($this->tokens)];
            $this->exclude($start, $last->line + substr_count($last->text, "\n"));
        }
    }

    /**
     * The line a tag ends, or the line below a tag on a line of its own, and
     * with it the statement starting there: the block it opens, the lines it
     * spans, or, for a case, everything up to the next case.
     */
    private function tag(int $at): void
    {
        $before = $this->significantBefore($at);
        $line = $before !== null && $this->tokens[$before]->line === $this->tokens[$at]->line
            ? $this->tokens[$at]->line
            : $this->tokens[$this->significantAfter($at) ?? $at]->line;
        $first = $this->firstOnLine($line);

        while ($first !== null && $this->tokens[$first]->text === '}') {
            $first = $this->significantAfter($first);
        }

        if ($first === null || $this->tokens[$first]->line < $line) {
            $this->exclude($line, $line);

            return;
        }

        $this->exclude($line, $this->isCase($first) ? $this->caseEnd($first) : $this->statementEndLine($first));
    }

    private function statementEndLine(int $from): int
    {
        $nesting = 0;

        for ($i = $from; $i < count($this->tokens); $i++) {
            $text = $this->tokens[$i]->text;

            if ($text === '(' || $text === '[') {
                $nesting++;
            } elseif ($text === ')' || $text === ']') {
                $nesting--;
            } elseif ($nesting === 0 && $this->tokens[$i]->id === 123) {
                return $this->tokens[$this->matchingClose($i)]->line;
            } elseif ($nesting === 0 && ($text === ';' || $text === '}')) {
                return $this->tokens[$text === ';' ? $i : ($this->significantBefore($i) ?? $i)]->line;
            }
        }

        return $this->tokens[$from]->line;
    }

    private function isCase(int $at): bool
    {
        $after = $this->significantAfter($at);

        return $this->tokens[$at]->is(T_CASE)
            || ($this->tokens[$at]->is(T_DEFAULT) && $after !== null && in_array($this->tokens[$after]->text, [':', ';'], true));
    }

    /**
     * The last line of a case: the line before the next case at its depth, or
     * before the brace that closes the switch.
     */
    private function caseEnd(int $from): int
    {
        $depth = 0;

        for ($i = $from + 1; $i < count($this->tokens); $i++) {
            $token = $this->tokens[$i];

            if ($token->is(self::OPENS)) {
                $depth++;
            } elseif ($token->text === '}' && --$depth < 0) {
                break;
            } elseif ($depth === 0 && $this->isCase($i)) {
                break;
            }
        }

        return $this->tokens[$this->significantBefore($i) ?? $from]->line;
    }

    /**
     * @return array{int, bool} where the group closes, and whether it holds ExcludeFromCoverage
     */
    private function attributeGroup(int $at): array
    {
        $brackets = 0;
        $parentheses = 0;
        $marked = false;

        for ($i = $at; $i < count($this->tokens); $i++) {
            $token = $this->tokens[$i];
            $brackets += match ($token->text) {
                '#[', '[' => 1,
                ']' => -1,
                default => 0,
            };
            $parentheses += match ($token->text) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };

            if ($brackets === 0) {
                break;
            }

            if ($parentheses === 0 && $token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE])) {
                $segments = explode('\\', $token->text);
                $marked = $marked || strtolower(end($segments)) === self::ATTRIBUTE;
            }
        }

        return [$i, $marked];
    }

    /**
     * @return array{int, string} where the namespace statement ends, and the prefix it gives names
     */
    private function namespaceAt(int $at): array
    {
        $name = '';

        for ($i = $at + 1; $i < count($this->tokens); $i++) {
            $token = $this->tokens[$i];

            if ($token->text === ';' || $token->text === '{') {
                break;
            }

            if (!$token->isIgnorable()) {
                $name .= $token->text;
            }
        }

        return [$i, $name === '' ? '' : $name . '\\'];
    }

    private function declaresAt(int $at): bool
    {
        $before = $this->significantBefore($at);

        return $this->nameAfter($at) !== null
            && ($before === null || !$this->tokens[$before]->is([T_DOUBLE_COLON, T_NEW]));
    }

    private function namesAClassAt(int $at): bool
    {
        $before = $this->significantBefore($at);

        return $before !== null && $this->tokens[$before]->is(T_DOUBLE_COLON);
    }

    private function nameAfter(int $at): ?string
    {
        $next = $this->significantAfter($at);

        if ($next !== null && $this->tokens[$next]->text === '&') {
            $next = $this->significantAfter($next);
        }

        $text = $next === null ? '' : $this->tokens[$next]->text;

        return preg_match('/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/', $text) === 1 ? $text : null;
    }

    /**
     * Where the body after a declaration opens, or the semicolon an abstract
     * method ends on.
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

    private function statementEnd(int $from): int
    {
        $open = $this->bodyOpen($from);

        return $this->tokens[$open]->text === ';' ? $open : $this->matchingClose($open);
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

    private function tagged(?int $doc): bool
    {
        return $doc !== null && preg_match(self::TAG, $this->tokens[$doc]->text) === 1;
    }

    private function firstOnLine(int $line): ?int
    {
        foreach ($this->tokens as $i => $token) {
            if ($token->line === $line && !$token->isIgnorable()) {
                return $i;
            }
        }

        return null;
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

    private function exclude(int $from, int $to): void
    {
        for ($line = $from; $line <= $to; $line++) {
            $this->excluded[$line] = true;
        }
    }
}
