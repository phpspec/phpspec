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

use PhpSpec\CommandLine;
use PhpSpec\Configuration;

/**
 * @internal
 * Which engine a run collects coverage with. The config may name one; with
 * none named, PCOV when it is enabled, else Xdebug in coverage mode. A
 * phpspec this one starts is told the same way, by php options.
 */
final readonly class CoverageEngines
{
    private const array OFF = ['pcov.enabled=0', 'xdebug.mode=off'];

    /** Set in the environment of a phpspec run again to trace what PCOV did not, so that one runs no other */
    public const string RESTARTED = 'PHPSPEC_PCOV_RESTARTED';

    /**
     * @param CoverageAdapter|null $named the engine the config names, or null for whichever is enabled
     * @param string $invocation the command line this process was started by, which the remedies repeat
     * @param list<string> $command that command line, word by word, to run again under PCOV; empty when it must not be
     */
    public function __construct(
        private ?CoverageAdapter $named,
        private PhpRuntime $php,
        private string $invocation,
        private array $command = [],
    ) {}

    /**
     * The engines of the process phpspec runs in, for the project it runs.
     *
     * @param array<int, mixed>|null $argv the command line the process was started by, or null for the process's own
     */
    public static function of(Configuration $config, ?array $argv = null): self
    {
        $argv ??= $_SERVER['argv'] ?? [];
        $words = array_values(array_map('strval', array_filter(is_array($argv) ? $argv : [], 'is_scalar')));

        return new self($config->coverageAdapter(), new LivePhpRuntime(), (new CommandLine(null))->for(...$words), getenv(self::RESTARTED) === false ? $words : []);
    }

    /**
     * The engine this process collects with, or why it cannot.
     *
     * @param list<string> $traced the directories the run reports on
     */
    public function inThisProcess(array $traced): CoverageAdapter|CoverageRefusal
    {
        $adapter = $this->named ?? $this->firstAdapterThat($this->php->enabled(...));

        if ($adapter === null) {
            return new CoverageRefusal(
                'Code coverage requires PCOV or Xdebug in coverage mode',
                $this->firstAdapterThat($this->php->loaded(...))?->remedy($this->invocation),
            );
        }

        if (!$this->php->enabled($adapter)) {
            return new CoverageRefusal($adapter->refusal(), $this->php->loaded($adapter) ? $adapter->remedy($this->invocation) : null);
        }

        if ($adapter === CoverageAdapter::Pcov) {
            return $this->outsidePcov($traced) ?? $adapter;
        }

        return $adapter;
    }

    /**
     * The engine a phpspec this process starts collects with: the named one,
     * else the first loaded, which the child switches on for itself.
     */
    public function forChildren(): ?CoverageAdapter
    {
        return $this->named ?? $this->firstAdapterThat($this->php->loaded(...));
    }

    /**
     * The php options under which a child collects with the engine, or
     * collects nothing.
     *
     * @return list<string>
     */
    public function switches(?CoverageAdapter $adapter): array
    {
        $settings = $adapter?->ini() ?? self::OFF;

        if ($adapter === CoverageAdapter::Pcov) {
            foreach (['pcov.directory', 'pcov.exclude'] as $name) {
                if ($this->php->ini($name) !== '') {
                    $settings[] = $name . '=' . $this->php->ini($name);
                }
            }
        }

        return array_merge(...array_map(static fn(string $setting): array => ['-d', $setting], $settings));
    }

    /**
     * @param callable(CoverageAdapter): bool $has
     */
    private function firstAdapterThat(callable $has): ?CoverageAdapter
    {
        foreach (CoverageAdapter::cases() as $adapter) {
            if ($has($adapter)) {
                return $adapter;
            }
        }

        return null;
    }

    /**
     * @param list<string> $traced
     */
    private function outsidePcov(array $traced): ?CoverageRefusal
    {
        $outside = array_values(array_filter($traced, fn(string $directory): bool => !$this->php->traces($directory)));

        if ($outside === []) {
            return null;
        }

        $directory = $this->directoryHoldingAll($traced);
        $options = [sprintf('Set pcov.directory=%s in php.ini to trace it in every run.', $directory)];

        if ($this->php->loaded(CoverageAdapter::Xdebug)) {
            $options[] = 'Set coverage_adapter: xdebug in the config to collect with Xdebug.';
        }

        return new CoverageRefusal(
            sprintf('PCOV traces %s, not %s', $this->php->pcovDirectory(), implode(' or ', array_map($this->name(...), $outside))),
            sprintf('php -d pcov.enabled=1 -d pcov.directory=%s %s', $directory, $this->invocation),
            $options,
            $this->command === [] ? [] : ['-d', 'pcov.enabled=1', '-d', 'pcov.directory=' . $directory, ...$this->command],
        );
    }

    /**
     * @param list<string> $directories
     */
    private function directoryHoldingAll(array $directories): string
    {
        $paths = array_map($this->parts(...), $directories);
        $common = array_shift($paths) ?? [];

        foreach ($paths as $parts) {
            $kept = [];

            foreach ($common as $i => $part) {
                if (($parts[$i] ?? null) !== $part) {
                    break;
                }

                $kept[] = $part;
            }

            $common = $kept;
        }

        return $common === [] ? '.' : implode('/', $common);
    }

    private function name(string $directory): string
    {
        $parts = $this->parts($directory);

        return $parts === [] ? '.' : implode('/', $parts);
    }

    /**
     * @return list<string>
     */
    private function parts(string $directory): array
    {
        return array_values(array_filter(
            explode('/', str_replace('\\', '/', $directory)),
            static fn(string $part): bool => $part !== '' && $part !== '.',
        ));
    }
}
