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

namespace PhpSpec\Mock;

/**
 * @internal
 * The file a call to a double was made from, which decides, as PHP decides,
 * whether its arguments are held to their types strictly or coerced.
 */
final readonly class CallingFile
{
    public function __construct(private string $path) {}

    public function declaresStrictTypes(): bool
    {
        if (!is_file($this->path)) {
            return false;
        }

        $head = (string) file_get_contents($this->path, false, null, 0, 8192);

        return preg_match('~^(?:#![^\n]*\n)?\s*<\?php\s*(?:(?:/\*.*?\*/|//[^\n]*|#(?!\[)[^\n]*)\s*)*declare\s*\(\s*strict_types\s*=\s*1\s*\)~is', $head) === 1;
    }
}
