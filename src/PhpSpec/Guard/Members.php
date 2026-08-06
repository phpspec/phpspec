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

namespace PhpSpec\Guard;

/**
 * @internal
 * Which member each line of a file belongs to, so a violation can be named
 * ("App\Basket::applyCoupon") instead of pointed at ("src/App/Basket.php:34").
 *
 * Read from the tokens rather than by reflection: the file need not be
 * loadable, which matters because the code guard is complaining about is often
 * the code that has just been written and never run.
 */
final class Members
{
    /**
     * Every line that sits inside a method, mapped to the name of the member
     * it sits in. Lines outside any member are absent.
     *
     * @return array<int, string>
     */
    public static function in(string $source): array
    {
        $tokens = @token_get_all($source);
        $namespace = '';
        $class = null;
        $names = [];

        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = self::nameAfter($tokens, $i) . '\\';

                continue;
            }

            if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                // "Foo::class" is not a declaration; only a name makes one.
                $name = self::nameAfter($tokens, $i);
                if ($name !== '') {
                    $class = $namespace . $name;
                }

                continue;
            }

            if ($token[0] === T_FUNCTION && $class !== null) {
                $method = self::nameAfter($tokens, $i);
                if ($method === '') {
                    continue;
                }

                foreach (self::body($tokens, $i) as $line) {
                    $names[$line] = $class . '::' . $method;
                }
            }
        }

        return $names;
    }

    /**
     * The identifier following a keyword, or an empty string when what follows
     * is not one (an anonymous class, a closure, a "::class" constant).
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private static function nameAfter(array $tokens, int $from): string
    {
        for ($i = $from + 1; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }

            if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED], true)) {
                return $token[1];
            }

            return '';
        }

        return '';
    }

    /**
     * The lines a function's body spans, from its signature to its closing
     * brace, or none when it has no body (an interface or abstract method).
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     * @return list<int>
     */
    private static function body(array $tokens, int $from): array
    {
        $depth = 0;
        $started = false;
        $first = is_array($tokens[$from]) ? $tokens[$from][2] : 0;
        $last = $first;

        for ($i = $from; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;
            $line = is_array($token) ? $token[2] : $last;

            if ($text === ';' && !$started) {
                // Declared without a body.
                return [];
            }

            if ($text === '{') {
                $depth++;
                $started = true;
            } elseif ($text === '}') {
                $depth--;
                if ($started && $depth === 0) {
                    return range($first, $line + substr_count($text, "\n"));
                }
            }

            $last = $line + substr_count($text, "\n");
        }

        return $started ? range($first, $last) : [];
    }
}
