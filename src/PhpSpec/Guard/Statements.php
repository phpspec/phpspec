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
 * Which lines of a file carry logic, for the one case coverage cannot answer:
 * a file the run never loaded at all, which is exactly the shape of a class
 * written and never specified.
 *
 * Deliberately a reading of the text rather than of the syntax tree. It is
 * only ever consulted about a file that has no coverage whatsoever, where the
 * question is not "which of these lines is untested" but "does this file
 * contain any logic at all", and the cost of counting one line too many is an
 * extra line in a report that was going to be made anyway.
 */
final class Statements
{
    /** Lines that declare rather than do. */
    private const DECLARATIONS = ['namespace', 'use', 'declare', 'class', 'interface', 'trait', 'enum', 'final', 'abstract', 'readonly', 'public', 'protected', 'private', 'static', 'function', 'case', 'const', 'require', 'require_once', 'include', 'include_once'];

    /**
     * The lines of this source that do something.
     *
     * @param list<string> $lines
     * @return list<int>
     */
    public static function in(array $lines): array
    {
        $logic = [];

        foreach ($lines as $index => $text) {
            if (self::isLogic($text)) {
                $logic[] = $index + 1;
            }
        }

        return $logic;
    }

    private static function isLogic(string $text): bool
    {
        $text = trim($text);

        if ($text === '' || $text === '<?php' || $text === '?>') {
            return false;
        }

        // A comment, or a line of one of the shapes a docblock is made of.
        if (preg_match('#^(//|\#|/\*|\*)#', $text) === 1) {
            return false;
        }

        // Punctuation holding a block together says nothing on its own.
        if (preg_match('/^[\{\}\)\(\],;]+$/', $text) === 1) {
            return false;
        }

        $first = strtolower((string) strtok($text, " \t(:;"));

        return !in_array($first, self::DECLARATIONS, true);
    }
}
