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

namespace PhpSpec\Coverage;

/**
 * @internal
 * The coverage engines of the process phpspec runs in.
 */
final class LivePhpRuntime implements PhpRuntime
{
    public function loaded(CoverageAdapter $adapter): bool
    {
        return extension_loaded($adapter->value);
    }

    public function enabled(CoverageAdapter $adapter): bool
    {
        if (!$this->loaded($adapter)) {
            return false;
        }

        return match ($adapter) {
            CoverageAdapter::Pcov => filter_var($this->ini('pcov.enabled'), FILTER_VALIDATE_BOOL),
            CoverageAdapter::Xdebug => in_array('coverage', xdebug_info('mode'), true),
        };
    }

    /**
     * The pcov.directory setting, else the directory PCOV picks for itself:
     * src, lib or app under the working directory, else all of it.
     */
    public function pcovDirectory(): string
    {
        $named = $this->ini('pcov.directory');

        if ($named !== '') {
            return $named;
        }

        foreach (['src', 'lib', 'app'] as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return '.';
    }

    public function traces(string $directory): bool
    {
        $traced = realpath($this->pcovDirectory());
        $asked = realpath($directory);

        if ($traced === false || $asked === false) {
            return true;
        }

        $traced = rtrim(str_replace('\\', '/', $traced), '/');
        $asked = str_replace('\\', '/', $asked);

        return $asked === $traced || str_starts_with($asked, $traced . '/');
    }

    public function ini(string $name): string
    {
        $value = ini_get($name);

        return is_string($value) ? $value : '';
    }
}
