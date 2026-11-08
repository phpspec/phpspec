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

use PhpSpec\Filesystem;
use stdClass;

/**
 * @internal
 * A project's composer.json, for the one change PhpSpec makes to it: telling
 * Composer to load the classes PhpSpec writes by their whole names, and
 * whether a mapping of its stands in the way.
 */
final readonly class ComposerJson
{
    public function __construct(
        private string $path,
        private Filesystem $filesystem,
    ) {}

    /**
     * Maps every class below the directory by its whole name, "" to it under
     * autoload.psr-4, in a composer.json that has no such fallback yet and
     * autoloads by no rule PhpSpec does not follow, PSR-0 or a classmap; the
     * rest is written as it was. What it added, said for the person who has
     * to dump the autoloader, or null when it added none.
     */
    public function autoloadBelow(string $directory): ?string
    {
        $directory = trim(str_replace('\\', '/', $directory), '/');
        $source = $this->filesystem->read($this->path);
        $composer = json_decode($source);

        if ($directory === '' || !$composer instanceof stdClass || !$this->takesFallback($composer)) {
            return null;
        }

        $autoload = ($composer->autoload ?? null) instanceof stdClass ? $composer->autoload : new stdClass();
        $psr4 = ($autoload->{'psr-4'} ?? null) instanceof stdClass ? get_object_vars($autoload->{'psr-4'}) : [];
        $autoload->{'psr-4'} = json_decode((string) json_encode($psr4 + ['' => $directory . '/']));
        $composer->autoload = $autoload;

        $this->filesystem->write($this->path, $this->encoded($composer, $source));

        return sprintf('Composer autoload was updated to include "": "%s/". Run composer dumpautoload next.', $directory);
    }

    /**
     * Why a class under no namespace composer.json maps cannot be written
     * below the directory by its whole name, or null when it can.
     */
    public function refusal(string $fqcn, string $directory): ?string
    {
        $directory = trim(str_replace('\\', '/', $directory), '/');
        $claim = $directory === '' ? null : ComposerAutoload::fromJson($this->filesystem->read($this->path))->claimOn($directory);

        return $claim === null ? null : sprintf('%s cannot be written under %s/: %s.', ltrim($fqcn, '\\'), $directory, $claim);
    }

    private function takesFallback(stdClass $composer): bool
    {
        $autoload = $composer->autoload ?? null;

        if (!$autoload instanceof stdClass) {
            return true;
        }

        if ($this->declares($autoload->{'psr-0'} ?? null) || $this->declares($autoload->classmap ?? null)) {
            return false;
        }

        $psr4 = $autoload->{'psr-4'} ?? null;

        return !$psr4 instanceof stdClass || !array_key_exists('', get_object_vars($psr4));
    }

    private function declares(mixed $rules): bool
    {
        return (is_array($rules) && $rules !== []) || ($rules instanceof stdClass && get_object_vars($rules) !== []);
    }

    /**
     * The JSON as Composer writes it, indented as the file was, ending as it did.
     */
    private function encoded(stdClass $composer, string $source): string
    {
        $json = (string) json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $indent = preg_match('~^([ \t]+)"~m', $source, $match) === 1 ? $match[1] : '    ';

        if ($indent !== '    ') {
            $json = (string) preg_replace_callback('~^(?: {4})+~m', static fn(array $spaces): string => str_repeat($indent, intdiv(strlen($spaces[0]), 4)), $json);
        }

        return str_ends_with($source, "\n") ? $json . "\n" : $json;
    }
}
