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
 * The classes a source file imports with `use`, read from its tokens before
 * its first declaration: what it talks to, by name. Functions, constants and
 * grouped imports are left out, as is anything a closure or a trait `use`s.
 */
final readonly class Imports
{
    public function __construct(private string $source) {}

    /**
     * @return list<string>
     */
    public function classes(): array
    {
        $tokens = token_get_all($this->source);
        $classes = [];

        for ($i = 0; $i < count($tokens); $i++) {
            $kind = is_array($tokens[$i]) ? $tokens[$i][0] : null;

            if (in_array($kind, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_FUNCTION], true)) {
                break;
            }

            if ($kind === T_USE) {
                [$imported, $i] = $this->importedFrom($tokens, $i + 1);
                $classes = [...$classes, ...$imported];
            }
        }

        return $classes;
    }

    /**
     * The class names one `use` statement imports, and where it ends.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     * @return array{0: list<string>, 1: int}
     */
    private function importedFrom(array $tokens, int $from): array
    {
        $names = [];
        $name = '';
        $aliased = false;

        for ($i = $from; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if ($token === ';' || $token === ',') {
                $names[] = $name;
                $name = '';
                $aliased = false;

                if ($token === ';') {
                    break;
                }

                continue;
            }

            if ($token === '{' || (is_array($token) && in_array($token[0], [T_FUNCTION, T_CONST], true))) {
                $names = [];
                $name = '';

                while ($i < count($tokens) && $tokens[$i] !== ';') {
                    $i++;
                }

                break;
            }

            if (is_array($token) && $token[0] === T_AS) {
                $aliased = true;
            } elseif (is_array($token) && !$aliased && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $name .= ltrim($token[1], '\\');
            }
        }

        return [array_values(array_filter($names, static fn(string $name): bool => $name !== '')), $i];
    }
}
