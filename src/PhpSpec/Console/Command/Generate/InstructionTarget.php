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
    private const STOP_WORDS = ['a', 'an', 'the', 'feature', 'scenario', 'story', 'describing', 'description', 'for', 'about', 'of', 'to', 'in', 'on', 'at', 'that', 'which', 'where'];

    /**
     * @return array{path: string, type: 'feature'|'spec'|'code'}|null
     */
    public static function parse(string $instruction): ?array
    {
        if (preg_match('~[A-Za-z0-9_./-]+\.(?:feature|php)~', $instruction, $matches)) {
            $path = $matches[0];
            $type = match (true) {
                str_ends_with($path, '.feature') => 'feature',
                str_ends_with($path, '.spec.php') => 'spec',
                default => 'code',
            };

            return ['path' => $path, 'type' => $type];
        }

        // No explicit path: infer a feature target from feature-intent wording,
        // deriving a slug filename from the instruction's subject.
        if (preg_match('~\b(?:feature|scenario|story)\b~i', $instruction)) {
            $slug = self::slug($instruction);
            if ($slug !== '') {
                return ['path' => 'features/' . $slug . '.feature', 'type' => 'feature'];
            }
        }

        // No explicit path: infer a spec or code target from intent wording and
        // the class named in the instruction, so the path comes from the user's
        // words rather than the model echoing a prompt example.
        $class = self::classToken($instruction);
        if ($class !== null && preg_match('~\bspec\b~i', $instruction)) {
            return ['path' => 'spec/' . str_replace('\\', '/', $class) . '.spec.php', 'type' => 'spec'];
        }

        if ($class !== null && preg_match('~\b(?:implement|method|function)\b~i', $instruction)) {
            return ['path' => 'src/' . str_replace('\\', '/', $class) . '.php', 'type' => 'code'];
        }

        return null;
    }

    private static function classToken(string $instruction): ?string
    {
        return preg_match('~\b([A-Z][A-Za-z0-9]*(?:\\\\[A-Z][A-Za-z0-9]*)*)\b~', $instruction, $matches) ? $matches[1] : null;
    }

    private static function slug(string $instruction): string
    {
        $words = preg_split('~[^a-z0-9]+~', strtolower($instruction), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_filter($words, static fn(string $word): bool => !in_array($word, self::STOP_WORDS, true)));

        return implode('_', array_slice($words, 0, 6));
    }
}
