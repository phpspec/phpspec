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

namespace PhpSpec;

/**
 * @internal
 * The id a run gives an example or a scenario: a hash of the file it lives in
 * and its full name, the titles leading to it joined by " > ". It holds across
 * edits that move lines, tells apart namesakes in different files, and
 * addresses one example among several a loop declares on one line.
 */
final class ExampleId
{
    public static function of(string $name, ?string $file): string
    {
        return substr(sha1(($file === null ? '' : ProjectRoot::here()->relative($file) . "\n") . $name), 0, 12);
    }
}
