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

namespace PhpSpec\CodeGeneration;

/**
 * @internal
 * What PhpSpec will accept as the name of something it is about to write.
 *
 * A generated file names its subject in code: `describe(App\Basket::class, ...)`.
 * A name PHP cannot parse therefore does not produce a bad spec, it produces a
 * file that stops the suite from loading at all, so the refusal has to happen
 * before anything is written. One statement of the rule, so every writer refuses
 * the same names and every caller can report the same reason.
 */
final class PhpName
{
    /** A single PHP identifier: a letter or underscore, then letters, digits or underscores. */
    private const IDENTIFIER = '/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/';

    /**
     * Why this cannot name a class, or null when it can. Namespace separators
     * are accepted either way round, since the commands take "App\Basket" and
     * the generators pass "App/Basket".
     *
     * @param string $name the class name as it was given
     */
    public static function classProblem(string $name): ?string
    {
        if (trim($name) === '') {
            return 'No class name was given.';
        }

        foreach (preg_split('~[\\\\/]~', $name) ?: [] as $segment) {
            if (preg_match(self::IDENTIFIER, $segment) !== 1) {
                return sprintf('"%s" is not a valid class name.', $name);
            }
        }

        return null;
    }

    /**
     * Why this cannot name a method, or null when it can. A method is one
     * identifier: no namespace, no separators.
     *
     * @param string $name the method name as it was given
     */
    public static function methodProblem(string $name): ?string
    {
        if (trim($name) === '') {
            return 'No method name was given.';
        }

        if (preg_match(self::IDENTIFIER, $name) !== 1) {
            return sprintf('"%s" is not a valid method name.', $name);
        }

        return null;
    }
}
