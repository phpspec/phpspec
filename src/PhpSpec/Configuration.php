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

use PhpSpec\Ai\ProviderFactory;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads and provides access to project configuration from phpspec.yaml, phpspec.yml,
 * phpspec.json, or phpspec.php (in priority order), or from an explicit config
 * file passed with --config.
 */
final class Configuration
{
    /** @var array<string, mixed> parsed configuration values */
    private array $config = [];

    /**
     * @param string $rootDir project root directory containing config files
     * @param Filesystem|null $filesystem injectable filesystem for testability
     * @param string|null $configFile explicit config file path; when set, the working directory cascade is skipped
     */
    private Filesystem $fs;

    public function __construct(
        private string $rootDir,
        ?Filesystem $filesystem = null,
        private readonly ?string $configFile = null,
    ) {
        $this->fs = $filesystem ?? new RealFilesystem();
        $this->load();
    }

    /**
     * Extracts the --config option value from raw argv tokens, supporting the
     * "--config=FILE", "--config FILE" and "-c FILE" forms.
     *
     * @param array<int, mixed> $argv raw argv tokens
     * @return string|null the config file path, or null when not given
     */
    public static function configPathFromArgv(array $argv): ?string
    {
        $tokens = array_values($argv);

        foreach ($tokens as $i => $token) {
            if (!is_string($token)) {
                continue;
            }

            if (str_starts_with($token, '--config=')) {
                return substr($token, strlen('--config='));
            }

            if ($token === '--config' || $token === '-c') {
                $next = $tokens[$i + 1] ?? null;

                if (is_string($next) && $next !== '' && !str_starts_with($next, '-')) {
                    return $next;
                }
            }
        }

        return null;
    }

    /**
     * Loads the explicit config file when given, otherwise the first config
     * file found in the root directory: yaml > yml > json > php.
     */
    private function load(): void
    {
        if ($this->configFile !== null) {
            $this->loadFile($this->configFile);

            return;
        }

        $yamlPath = $this->rootDir . '/phpspec.yaml';
        $ymlPath = $this->rootDir . '/phpspec.yml';
        $jsonPath = $this->rootDir . '/phpspec.json';
        $phpPath = $this->rootDir . '/phpspec.php';

        if ($this->fs->exists($yamlPath)) {
            $this->config = Yaml::parse($this->fs->read($yamlPath)) ?? [];
        } elseif ($this->fs->exists($ymlPath)) {
            $this->config = Yaml::parse($this->fs->read($ymlPath)) ?? [];
        } elseif ($this->fs->exists($jsonPath)) {
            $content = $this->fs->read($jsonPath);
            $this->config = json_decode($content, true) ?? [];
        } elseif ($this->fs->exists($phpPath)) {
            $this->config = $this->fs->requirePhp($phpPath);
        }
    }

    /**
     * Loads configuration from an explicit file, resolving the parser from
     * the file extension.
     *
     * @param string $path the config file path
     * @throws RuntimeException when the file does not exist or has an unsupported extension
     */
    private function loadFile(string $path): void
    {
        if (!$this->fs->exists($path)) {
            throw new RuntimeException("Configuration file not found: $path");
        }

        $this->config = match (pathinfo($path, PATHINFO_EXTENSION)) {
            'yaml', 'yml' => Yaml::parse($this->fs->read($path)) ?? [],
            'json' => json_decode($this->fs->read($path), true) ?? [],
            'php' => $this->fs->requirePhp($path),
            default => throw new RuntimeException("Unsupported configuration file type: $path"),
        };
    }

    /**
     * Retrieves a configuration value by key.
     *
     * @param string $key configuration key
     * @param mixed $default fallback if key is not set
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Returns the spec directory path. Defaults to './spec'.
     */
    public function getSpecPath(): string
    {
        return $this->get('spec_path', './spec');
    }

    /**
     * Returns the source directory path. Defaults to './src'.
     * Falls back to the first suite's src key when no flat src_path is set.
     */
    public function getSrcPath(): string
    {
        if (isset($this->config['src_path'])) {
            return $this->config['src_path'];
        }
        if (isset($this->config['suites']) && is_array($this->config['suites'])) {
            foreach ($this->config['suites'] as $suite) {
                if (isset($suite['src'])) {
                    return $suite['src'];
                }
            }
        }
        return './src';
    }

    /**
     * Returns the PSR-4 namespace prefix mapped to the source directory.
     * When set, namespace segments matching the prefix are not reflected
     * in the directory structure during code generation.
     *
     * Reads from top-level `psr4_prefix` or per-suite `namespace` key.
     * Defaults to '' (PSR-0 behaviour: all segments become directories).
     */
    public function getPsr4Prefix(): string
    {
        if (isset($this->config['psr4_prefix']) && is_string($this->config['psr4_prefix'])) {
            return rtrim($this->config['psr4_prefix'], '\\');
        }
        if (isset($this->config['suites']) && is_array($this->config['suites'])) {
            foreach ($this->config['suites'] as $suite) {
                if (isset($suite['namespace']) && is_string($suite['namespace'])) {
                    return rtrim($suite['namespace'], '\\');
                }
            }
        }
        return '';
    }

    /**
     * Returns the spec file suffix. Defaults to '.spec.php'.
     */
    public function getSpecSuffix(): string
    {
        return $this->get('spec_suffix', '.spec.php');
    }

    /**
     * Returns the output format name. Defaults to 'pretty'.
     */
    public function getFormat(): string
    {
        return $this->get('format', 'pretty');
    }

    /**
     * Returns the bootstrap file path, or null if not configured.
     */
    public function getBootstrap(): ?string
    {
        return $this->get('bootstrap');
    }

    /**
     * Returns the base URL for browser testing, or null if not configured.
     */
    public function getBaseUrl(): ?string
    {
        return $this->get('base_url');
    }

    /**
     * Returns whether execution should halt on the first failure.
     */
    public function getStopOnFailure(): bool
    {
        return $this->get('stop_on_failure', false);
    }

    /**
     * Returns whether to stop on errors from configuration.
     */
    public function getStopOnError(): bool
    {
        return $this->get('stop_on_error', false);
    }

    /**
     * Returns whether to stop on warnings from configuration.
     */
    public function getStopOnWarning(): bool
    {
        return $this->get('stop_on_warning', false);
    }

    /**
     * Returns whether to stop on deprecations from configuration.
     */
    public function getStopOnDeprecation(): bool
    {
        return $this->get('stop_on_deprecation', false);
    }

    /**
     * Returns whether to stop on notices from configuration.
     */
    public function getStopOnNotice(): bool
    {
        return $this->get('stop_on_notice', false);
    }

    /**
     * Returns whether to stop on skipped examples from configuration.
     */
    public function getStopOnSkipped(): bool
    {
        return $this->get('stop_on_skipped', false);
    }

    /**
     * Builds a StopConditions value object from all configured stop_on_* flags.
     */
    public function getStopConditions(): StopConditions
    {
        return new StopConditions(
            onFailure: $this->getStopOnFailure(),
            onError: $this->getStopOnError(),
            onWarning: $this->getStopOnWarning(),
            onDeprecation: $this->getStopOnDeprecation(),
            onNotice: $this->getStopOnNotice(),
            onSkipped: $this->getStopOnSkipped(),
        );
    }

    /**
     * Returns named suites. If no suites key exists, synthesises a default suite
     * from the flat spec_path/src_path.
     *
     * @return array<string, array{paths?: string[], src?: string, steps?: string[]}>
     */
    public function getSuites(): array
    {
        if (isset($this->config['suites']) && is_array($this->config['suites'])) {
            return $this->config['suites'];
        }
        return ['default' => ['paths' => [$this->getSpecPath()], 'src' => $this->getSrcPath()]];
    }

    /**
     * Returns all load paths combined from all suites, comma-separated.
     *
     * A suite that names no paths of its own means the project's spec path: the
     * loader has always run it that way, and saying so here keeps the answer the
     * same wherever it is asked, including the header of a report written before
     * anything is loaded.
     */
    public function getAllLoadPaths(): string
    {
        $paths = [];
        foreach ($this->getSuites() as $suite) {
            $paths = array_merge($paths, $suite['paths'] ?? [$this->getSpecPath()]);
        }

        return implode(',', array_unique($paths));
    }

    /**
     * Returns the features directory path. Defaults to 'features/'.
     */
    public function getFeaturesPath(): string
    {
        return $this->get('features_path', 'features/');
    }

    /**
     * Returns the configured step definitions directory, searched in addition
     * to the features folder, or null when not configured.
     */
    public function getStepsPath(): ?string
    {
        return $this->get('steps_path');
    }

    /**
     * Returns the extensions configuration block.
     *
     * @return array<string, mixed>
     */
    public function getExtensions(): array
    {
        return $this->get('extensions', []);
    }

    /**
     * The keys a valid ai section may hold, in their canonical snake_case
     * spellings; the documented contract in one place.
     */
    private const AI_KEYS = ['provider', 'model', 'max_tokens', 'effort', 'api_key', 'base_url'];

    /**
     * What a guard section may say, and what it means when it says nothing.
     * One statement of the requirements: the defaults below and the diagnosis
     * in {@see guardSectionGap()} both read from it, so a key cannot be
     * accepted by one and unknown to the other.
     */
    private const GUARD_DEFAULTS = [
        'status' => 'off',
        'scope' => 'spec',
        'detection' => 'git',
        'standards' => 'phpspec',
        'paths' => ['src'],
        'allow' => [],
    ];

    /** The settings that name one of a fixed set of behaviours. */
    private const GUARD_CHOICES = [
        'status' => ['active', 'off'],
        'scope' => ['spec', 'story'],
        'detection' => ['git', 'mtime'],
    ];

    /** The settings that are lists of paths or globs. */
    private const GUARD_LISTS = ['paths', 'allow'];

    /**
     * Returns the guard configuration, every value present, or null when the
     * section is unusable ({@see guardConfigProblem()} says why). A project
     * that says nothing gets guard off, which is the only safe default: guard
     * fails a run, and nobody opts into that by omission.
     *
     * @return array{status: string, scope: string, detection: string, standards: string, paths: list<string>, allow: list<string>}|null
     */
    public function getGuardConfig(): ?array
    {
        $guard = $this->guardSection();
        if ($this->guardSectionGap($guard) !== null) {
            return null;
        }

        $config = self::GUARD_DEFAULTS;
        foreach ($guard as $key => $value) {
            $config[$key] = in_array($key, self::GUARD_LISTS, true) ? array_values($value) : $value;
        }

        /** @var array{status: string, scope: string, detection: string, standards: string, paths: list<string>, allow: list<string>} $config */
        return $config;
    }

    /**
     * Says what is wrong with the guard section, or null when it is usable. An
     * absent section is not a problem: it means guard is off.
     */
    public function guardConfigProblem(): ?string
    {
        return $this->guardSectionGap($this->guardSection());
    }

    /**
     * The guard section as written, or an empty one when it is absent.
     *
     * @return array<string, mixed>
     */
    private function guardSection(): array
    {
        $guard = $this->get('guard');

        return is_array($guard) ? $guard : [];
    }

    /**
     * The one place that decides whether a guard section can be used, so the
     * getter and the diagnosis can never disagree about it.
     *
     * @param array<string, mixed> $guard
     */
    private function guardSectionGap(array $guard): ?string
    {
        foreach (array_keys($guard) as $key) {
            if (!array_key_exists($key, self::GUARD_DEFAULTS)) {
                return $this->unknownGuardKey((string) $key);
            }
        }

        foreach (self::GUARD_CHOICES as $key => $allowed) {
            if (isset($guard[$key]) && !in_array($guard[$key], $allowed, true)) {
                return sprintf(
                    'The guard section\'s %s must be %s, not %s.',
                    $key,
                    self::naturalList($allowed, 'or'),
                    self::asWritten($guard[$key]),
                );
            }
        }

        foreach (self::GUARD_LISTS as $key) {
            if (!isset($guard[$key])) {
                continue;
            }

            if (!is_array($guard[$key]) || array_filter($guard[$key], 'is_string') !== $guard[$key]) {
                return sprintf('The guard section\'s %s must be a list of paths.', $key);
            }
        }

        if (isset($guard['standards']) && (!is_string($guard['standards']) || $guard['standards'] === '')) {
            return 'The guard section\'s standards must be "phpspec" or the path to a standard.';
        }

        return null;
    }

    /**
     * An unknown guard key, with the one it was probably meant to be.
     */
    /**
     * A rejected value as the reader wrote it. YAML turns "true" into a
     * boolean, and being told the setting must not be "1" sends them looking
     * for a 1 that appears nowhere in their file.
     */
    private static function asWritten(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_scalar($value) ? sprintf('"%s"', $value) : get_debug_type($value);
    }

    private function unknownGuardKey(string $key): string
    {
        $closest = null;
        $best = 4;
        foreach (array_keys(self::GUARD_DEFAULTS) as $known) {
            $distance = levenshtein($key, $known);
            if ($distance < $best) {
                $best = $distance;
                $closest = $known;
            }
        }

        if ($closest !== null) {
            return sprintf('Unknown guard key "%s". Did you mean "%s"?', $key, $closest);
        }

        return sprintf('Unknown guard key "%s". The known keys are %s.', $key, self::naturalList(array_keys(self::GUARD_DEFAULTS)));
    }

    /**
     * Returns the AI configuration block, or null when it is absent or
     * unusable ({@see aiConfigProblem()} says why). api_key is present for
     * every provider that authenticates with one; a local ollama has none.
     *
     * @return array{provider: string, model?: string, maxTokens?: int, effort?: string, base_url?: string, api_key?: string}|null
     */
    public function getAiConfig(): ?array
    {
        $ai = $this->normalisedAiSection();
        if ($ai === null || $this->aiSectionGap($ai) !== null) {
            return null;
        }

        // The gap check above vouched for every key and type, so the values
        // read straight through; nothing here is silently defaulted or dropped.
        $result = ['provider' => $ai['provider']];
        if (isset($ai['model'])) {
            $result['model'] = $ai['model'];
        }
        if (isset($ai['max_tokens'])) {
            $result['maxTokens'] = (int) $ai['max_tokens'];
        }
        if (isset($ai['effort'])) {
            $result['effort'] = $ai['effort'];
        }
        if (isset($ai['base_url'])) {
            $result['base_url'] = $ai['base_url'];
        }
        if (isset($ai['api_key'])) {
            $result['api_key'] = $ai['api_key'];
        }

        return $result;
    }

    /**
     * Registers PSR-4 autoloaders from the "autoload" config map (prefix => baseDir).
     */
    public function registerAutoloaders(): void
    {
        $autoload = $this->get('autoload');
        if (is_array($autoload)) {
            spl_autoload_register(function (string $class) use ($autoload) {
                foreach ($autoload as $prefix => $baseDir) {
                    if (str_starts_with($class, $prefix)) {
                        $relativeClass = substr($class, strlen($prefix));
                        $file = rtrim($baseDir, '/') . '/' . str_replace('\\', '/', $relativeClass) . '.php';
                        if (file_exists($file)) {
                            require $file;
                            return;
                        }
                    }
                }
            });
        }

        // Fallback PSR-4 autoloader for the src directory
        $srcPath = $this->getSrcPath();
        $srcDir = rtrim($this->rootDir, '/') . '/' . ltrim($srcPath, './');
        spl_autoload_register(function (string $class) use ($srcDir) {
            $file = $srcDir . '/' . str_replace('\\', '/', $class) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        });
    }

    /**
     * Returns the raw configuration as an associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->config;
    }

    /**
     * What stands between the user and a working AI config, or null when the
     * config is usable: a present-but-unusable section is told exactly what
     * the gap is, never that the section does not exist. The message comes
     * from the same check that makes {@see getAiConfig()} return null, so the
     * two can never disagree.
     */
    public function aiConfigProblem(): ?string
    {
        $ai = $this->normalisedAiSection();
        if ($ai === null) {
            return 'AI configuration required. Add an "ai" section to your phpspec config.';
        }

        return $this->aiSectionGap($ai);
    }

    /**
     * Whether the config declares an ai section at all, usable or not.
     */
    public function hasAiSection(): bool
    {
        return is_array($this->get('ai'));
    }

    /**
     * The first unmet requirement of the ai section as a user-facing message,
     * or null when the section is usable. The one statement of what a usable
     * ai config requires: the reader and the diagnosis both derive from it,
     * and the provider knowledge comes from the factory that constructs them,
     * so neither the message nor the acceptance can drift from reality.
     *
     * @param array<string, mixed> $ai the normalised ai section
     */
    private function aiSectionGap(array $ai): ?string
    {
        foreach (array_keys($ai) as $key) {
            if (!in_array($key, self::AI_KEYS, true)) {
                return $this->unknownAiKey((string) $key);
            }
        }

        if (!isset($ai['provider'])) {
            return $this->missingProvider();
        }

        if (!is_string($ai['provider']) || !in_array($ai['provider'], ProviderFactory::providers(), true)) {
            return sprintf(
                'Unknown ai provider "%s". The known providers are %s.',
                is_scalar($ai['provider']) ? (string) $ai['provider'] : get_debug_type($ai['provider']),
                self::naturalList(ProviderFactory::providers()),
            );
        }

        if (ProviderFactory::needsApiKey($ai['provider'])) {
            if (!isset($ai['api_key'])) {
                return 'The ai section is missing api_key. Add it to your phpspec config.';
            }

            if (!is_string($ai['api_key'])) {
                return 'The ai section\'s api_key must be a string. Quote it in your phpspec config.';
            }
        }

        if (isset($ai['model']) && !is_string($ai['model'])) {
            return 'The ai section\'s model must be a string.';
        }

        if (isset($ai['max_tokens']) && (!is_numeric($ai['max_tokens']) || (int) $ai['max_tokens'] <= 0)) {
            return 'The ai section\'s max_tokens must be a positive number.';
        }

        if (isset($ai['effort']) && (!is_string($ai['effort']) || $ai['effort'] === '')) {
            return 'The ai section\'s effort must be a non-empty string.';
        }

        if (isset($ai['base_url']) && !is_string($ai['base_url'])) {
            return 'The ai section\'s base_url must be a string.';
        }

        return null;
    }

    /**
     * The message for an unrecognised ai key: the nearest known key when the
     * spelling is close (a typo), the full list otherwise.
     */
    private function unknownAiKey(string $key): string
    {
        $closest = null;
        $best = 4;
        foreach (self::AI_KEYS as $known) {
            $distance = levenshtein($key, $known);
            if ($distance < $best) {
                $best = $distance;
                $closest = $known;
            }
        }

        if ($closest !== null) {
            return sprintf('Unknown ai key "%s". Did you mean "%s"?', $key, $closest);
        }

        return sprintf('Unknown ai key "%s". The known keys are %s.', $key, self::naturalList(self::AI_KEYS));
    }

    /**
     * The missing-provider message: when exactly one papi provider package is
     * installed, the error names it as the answer.
     */
    private function missingProvider(): string
    {
        $installed = ProviderFactory::installed();
        if (count($installed) === 1) {
            return sprintf('The ai section is missing provider. papi-ai/%s is installed, so set provider: %s.', $installed[0], $installed[0]);
        }

        return sprintf('The ai section is missing provider. Set it to %s.', self::naturalList(ProviderFactory::providers()));
    }

    /**
     * Joins words as prose: "google, anthropic, and openai".
     *
     * @param list<string> $items
     */
    private static function naturalList(array $items, string $conjunction = 'and'): string
    {
        if (count($items) <= 1) {
            return implode('', $items);
        }

        $last = array_pop($items);
        // Two items read as "active or off"; more take the comma before the
        // conjunction that a list of three or more wants.
        $separator = count($items) === 1 ? ' ' : ', ';

        return implode(', ', $items) . $separator . $conjunction . ' ' . $last;
    }

    /**
     * The raw ai section with hyphenated spellings of its keys folded onto the
     * canonical snake_case names (api-key reads as api_key, for every known
     * key), or null when the config has no ai section. Snake case stays the
     * documented spelling; the common YAML hyphen habit simply keeps working.
     *
     * @return array<string, mixed>|null
     */
    private function normalisedAiSection(): ?array
    {
        $ai = $this->get('ai');
        if (!is_array($ai)) {
            return null;
        }

        foreach (array_keys($ai) as $key) {
            $canonical = str_replace('-', '_', (string) $key);
            if ($canonical !== $key && in_array($canonical, self::AI_KEYS, true) && !isset($ai[$canonical])) {
                $ai[$canonical] = $ai[$key];
                unset($ai[$key]);
            }
        }

        return $ai;
    }
}
