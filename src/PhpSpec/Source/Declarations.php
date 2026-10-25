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

/**
 * @internal
 * The interfaces and enums a source file declares, read from its tokens:
 * each interface with the interfaces it extends, each enum with those it
 * implements, every name resolved as PHP resolves it, against the namespace
 * and the imports in force where it is written.
 */
final class Declarations
{
    private const NAMES = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE];

    private readonly Tokens $tokens;

    private string $namespace = '';

    /** @var array<string, string> what each import is called here, lower-cased, to the name it imports */
    private array $imports = [];

    /** @var array<string, list<string>> interface, lower-cased, to the interfaces it extends, lower-cased */
    private array $interfaces = [];

    /** @var array<string, list<string>> enum, as declared, to the interfaces it implements, lower-cased */
    private array $enums = [];

    public function __construct(string $source)
    {
        $this->tokens = Tokens::of($source);
        $this->read();
    }

    /**
     * @return array<string, list<string>>
     */
    public function interfaces(): array
    {
        return $this->interfaces;
    }

    /**
     * @return array<string, list<string>>
     */
    public function enums(): array
    {
        return $this->enums;
    }

    private function read(): void
    {
        $list = $this->tokens->list;

        for ($at = 0; $at < count($list); $at++) {
            $token = $list[$at];

            if ($token->is(T_NAMESPACE) && !$this->followedBy($at, T_NS_SEPARATOR)) {
                $at = $this->namespaceAt($at);
            } elseif ($token->is(T_USE)) {
                $at = $this->importsAt($at);
            } elseif ($token->is([T_INTERFACE, T_ENUM])) {
                $at = $this->declarationAt($at);
            } elseif ($token->is([T_CLASS, T_TRAIT]) && !$this->precededBy($at, T_DOUBLE_COLON)) {
                $at = $this->tokens->matchingClose($this->tokens->bodyOpen($at));
            } elseif ($token->is(T_FUNCTION)) {
                $open = $this->tokens->bodyOpen($at);
                $at = $list[$open]->text === ';' ? $open : $this->tokens->matchingClose($open);
            }
        }
    }

    /**
     * Starts a namespace, its imports afresh: where its name ends.
     */
    private function namespaceAt(int $at): int
    {
        $name = $this->tokens->significantAfter($at);
        $this->imports = [];

        if ($name !== null && $this->tokens->list[$name]->is(self::NAMES)) {
            $this->namespace = $this->tokens->list[$name]->text;

            return $name;
        }

        $this->namespace = '';

        return $at;
    }

    /**
     * Reads the classes a use statement imports, one by one or as a group,
     * leaving functions and constants: where the statement ends.
     */
    private function importsAt(int $at): int
    {
        $list = $this->tokens->list;
        $first = $this->tokens->significantAfter($at);

        if ($first !== null && $list[$first]->is([T_FUNCTION, T_CONST])) {
            return $this->statementEnd($at);
        }

        $prefix = '';
        $name = null;
        $alias = null;

        for ($i = $at + 1; $i < count($list); $i++) {
            $token = $list[$i];

            if ($token->is(self::NAMES) && $alias === false) {
                $alias = $token->text;
            } elseif ($token->is(self::NAMES)) {
                $name = ($name ?? '') . $token->text;
            } elseif ($token->is(T_AS)) {
                $alias = false;
            } elseif ($token->text === '{') {
                $prefix = rtrim((string) $name, '\\') . '\\';
                $name = null;
            } elseif (in_array($token->text, [',', '}', ';'], true)) {
                if ($name !== null) {
                    $this->import($prefix . $name, is_string($alias) ? $alias : null);
                }

                $name = null;
                $alias = null;

                if ($token->text === ';') {
                    return $i;
                }
            } elseif ($token->is(T_NS_SEPARATOR)) {
                $name = ($name ?? '') . '\\';
            }
        }

        return count($list) - 1;
    }

    private function import(string $name, ?string $alias): void
    {
        $name = ltrim($name, '\\');
        $segments = explode('\\', $name);

        $this->imports[strtolower($alias ?? end($segments))] = $name;
    }

    /**
     * An interface or an enum, with the names after extends or implements:
     * where its body closes.
     */
    private function declarationAt(int $at): int
    {
        $list = $this->tokens->list;
        $nameAt = $this->tokens->significantAfter($at);
        $open = $this->tokens->bodyOpen($at);

        if ($nameAt === null || !$list[$nameAt]->is(T_STRING)) {
            return $at;
        }

        $parents = [];
        $listing = false;

        for ($i = $nameAt + 1; $i < $open; $i++) {
            if ($list[$i]->is([T_EXTENDS, T_IMPLEMENTS])) {
                $listing = true;
            } elseif ($listing && $list[$i]->is(self::NAMES)) {
                $parents[] = strtolower($this->resolved($list[$i]->text, $list[$i]->id));
            }
        }

        $name = ltrim($this->namespace . '\\' . $list[$nameAt]->text, '\\');

        if ($list[$at]->is(T_INTERFACE)) {
            $this->interfaces[strtolower($name)] = $parents;
        } else {
            $this->enums[$name] = $parents;
        }

        return $this->tokens->matchingClose($open);
    }

    private function resolved(string $name, int $kind): string
    {
        if ($kind === T_NAME_FULLY_QUALIFIED) {
            return ltrim($name, '\\');
        }

        if ($kind === T_NAME_RELATIVE) {
            return ltrim($this->namespace . '\\' . substr($name, strlen('namespace\\')), '\\');
        }

        $segments = explode('\\', $name);
        $imported = $this->imports[strtolower($segments[0])] ?? null;

        if ($imported !== null) {
            return implode('\\', [$imported, ...array_slice($segments, 1)]);
        }

        return ltrim($this->namespace . '\\' . $name, '\\');
    }

    private function statementEnd(int $from): int
    {
        for ($i = $from; $i < count($this->tokens->list); $i++) {
            if ($this->tokens->list[$i]->text === ';') {
                return $i;
            }
        }

        return count($this->tokens->list) - 1;
    }

    private function followedBy(int $at, int $kind): bool
    {
        $next = $this->tokens->significantAfter($at);

        return $next !== null && $this->tokens->list[$next]->is($kind);
    }

    private function precededBy(int $at, int $kind): bool
    {
        $before = $this->tokens->significantBefore($at);

        return $before !== null && $this->tokens->list[$before]->is($kind);
    }
}
