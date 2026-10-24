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

namespace PhpSpec\Process;

/**
 * @internal
 * A php started on the binary running this one and set up as this one is:
 * the same settings, and the extensions its command line loaded.
 */
final class ChildPhp
{
    /** @var list<string>|null */
    private ?array $options = null;

    /**
     * The php options that set a php started afresh up as this one, asked of
     * a fresh php once.
     *
     * @return list<string>
     */
    public function options(): array
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $fresh = $this->freshSetup();
        $this->options = $fresh === null ? [] : (new PhpSetup($this->settings(ini_get_all(null, false)), get_loaded_extensions(), get_loaded_extensions(true)))->over($fresh);

        return $this->options;
    }

    /**
     * The fatal error a piece of code ends on in a child php set up as this
     * one, in PHP's own words, or null when it ends without one.
     */
    public function fatalOf(string $code): ?string
    {
        $process = proc_open([PHP_BINARY, ...$this->options(), '-d', 'display_errors=stderr', '-d', 'log_errors=0', '-d', 'html_errors=0', '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (!is_resource($process)) {
            return null;
        }

        stream_get_contents($pipes[1]);
        $errors = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return preg_match('/Fatal error: +(.+) in (?:Command line code|Unknown) on line \d+\s*$/m', $errors, $fatal) === 1 ? $fatal[1] : null;
    }

    /**
     * How a php started afresh on the same binary is set up, asked of one, or
     * null when it cannot be asked.
     */
    private function freshSetup(): ?PhpSetup
    {
        $process = proc_open([PHP_BINARY, '-r', 'echo json_encode([ini_get_all(null, false), get_loaded_extensions(), get_loaded_extensions(true)]);'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (!is_resource($process)) {
            return null;
        }

        $printed = (string) stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        $setup = json_decode($printed, true);

        if (!is_array($setup) || !is_array($setup[1] ?? null) || !is_array($setup[2] ?? null)) {
            return null;
        }

        return new PhpSetup($this->settings($setup[0] ?? null), $this->names($setup[1]), $this->names($setup[2]));
    }

    /**
     * @param array<mixed> $names
     * @return list<string>
     */
    private function names(array $names): array
    {
        return array_values(array_filter($names, 'is_string'));
    }

    /**
     * @return array<string, string|null>
     */
    private function settings(mixed $all): array
    {
        $settings = [];

        foreach (is_array($all) ? $all : [] as $name => $value) {
            if (is_string($name) && (is_string($value) || $value === null)) {
                $settings[$name] = $value;
            }
        }

        return $settings;
    }
}
