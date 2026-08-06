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
 * The methods a source file declares, and the lines each of them occupies.
 *
 * Two questions are asked of this. Guard asks which member a line sits in, so
 * a violation can be named ("App\Basket::applyCoupon") instead of pointed at
 * ("src/App/Basket.php:34"). The JSON coverage report asks for every span, so
 * a mutation testing tool knows the boundaries of the method it is mutating.
 *
 * Read from the tokens rather than by reflection: the file need not be
 * loadable, which matters because the code guard complains about is often the
 * code that has just been written and never run.
 */
final readonly class Members
{
    /**
     * @param list<array{class: string, method: string, start: int, end: int}> $declared
     */
    private function __construct(private array $declared) {}

    public static function in(string $source): self
    {
        $tokens = @token_get_all($source);
        $namespace = '';
        $class = null;
        $declared = [];

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
                $body = $method === '' ? null : self::body($tokens, $i);

                if ($body !== null) {
                    $declared[] = ['class' => $class, 'method' => $method] + $body;
                }
            }
        }

        return new self($declared);
    }

    /**
     * The member this line belongs to, or nothing when it belongs to none: a
     * class declaration, a property, the blank line between two methods.
     */
    public function at(int $line): ?string
    {
        $name = null;

        foreach ($this->declared as $member) {
            if ($line >= $member['start'] && $line <= $member['end']) {
                $name = $member['class'] . '::' . $member['method'];
            }
        }

        return $name;
    }

    /**
     * Where each method starts and ends, by name.
     *
     * The bare name is what a consumer knows a method by, but a file may
     * declare two classes, and then one name would answer for lines that
     * belong to the other. Those names are qualified instead, so a lookup
     * finds nothing rather than finding the wrong thing.
     *
     * @return array<string, array{start: int, end: int}>
     */
    public function spans(): array
    {
        $spans = [];

        foreach ($this->declared as $member) {
            $name = $this->sharedName($member['method'])
                ? $member['class'] . '::' . $member['method']
                : $member['method'];

            $spans[$name] = ['start' => $member['start'], 'end' => $member['end']];
        }

        return $spans;
    }

    private function sharedName(string $method): bool
    {
        $classes = [];

        foreach ($this->declared as $member) {
            if ($member['method'] === $method) {
                $classes[$member['class']] = true;
            }
        }

        return count($classes) > 1;
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
     * The first and last line of a function, from its signature to the brace
     * that closes it, or nothing when it has no body to occupy (an interface
     * or abstract method).
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     * @return array{start: int, end: int}|null
     */
    private static function body(array $tokens, int $from): ?array
    {
        $depth = 0;
        $started = false;
        $start = is_array($tokens[$from]) ? $tokens[$from][2] : 0;
        $last = $start;

        for ($i = $from; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;
            $line = is_array($token) ? $token[2] : $last;

            if ($text === ';' && !$started) {
                return null;
            }

            if ($text === '{') {
                $depth++;
                $started = true;
            } elseif ($text === '}') {
                $depth--;
                if ($started && $depth === 0) {
                    return ['start' => $start, 'end' => $line];
                }
            }

            $last = $line + substr_count($text, "\n");
        }

        return $started ? ['start' => $start, 'end' => $last] : null;
    }
}
