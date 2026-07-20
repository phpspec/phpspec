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

namespace PhpSpec\Console\Command\Generate;

/**
 * @internal
 * Extracts an explicit target file named in a `/generate` instruction and
 * classifies it by extension, so the path and artifact type come from the user's
 * own words rather than the model's guess. Null when no path is named.
 */
final class InstructionTarget
{
    /**
     * @return array{path: string, type: 'feature'|'spec'|'code'}|null
     */
    public static function parse(string $instruction): ?array
    {
        if (!preg_match('~[A-Za-z0-9_./-]+\.(?:feature|php)~', $instruction, $matches)) {
            return null;
        }

        $path = $matches[0];
        $type = match (true) {
            str_ends_with($path, '.feature') => 'feature',
            str_ends_with($path, '.spec.php') => 'spec',
            default => 'code',
        };

        return ['path' => $path, 'type' => $type];
    }
}
