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
     */
    public function getAllLoadPaths(): string
    {
        $paths = [];
        foreach ($this->getSuites() as $suite) {
            $paths = array_merge($paths, $suite['paths'] ?? []);
        }
        return implode(',', $paths);
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
     * Returns the AI configuration block, or null if not configured.
     *
     * @return array{provider: string, model?: string, maxTokens?: int, effort?: string, api_key: string}|null
     */
    public function getAiConfig(): ?array
    {
        $ai = $this->normalisedAiSection();
        if ($ai === null || !isset($ai['api_key']) || !is_string($ai['api_key'])) {
            return null;
        }
        $result = [
            'provider' => isset($ai['provider']) && is_string($ai['provider']) ? $ai['provider'] : 'openai',
        ];
        if (isset($ai['model']) && is_string($ai['model'])) {
            $result['model'] = $ai['model'];
        }
        if (isset($ai['max_tokens']) && is_numeric($ai['max_tokens']) && (int) $ai['max_tokens'] > 0) {
            $result['maxTokens'] = (int) $ai['max_tokens'];
        }
        if (isset($ai['effort']) && is_string($ai['effort']) && $ai['effort'] !== '') {
            $result['effort'] = $ai['effort'];
        }
        $result['api_key'] = $ai['api_key'];
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
     * config is usable: a present-but-keyless section is told which key is
     * missing, never that the section does not exist.
     */
    public function aiConfigProblem(): ?string
    {
        if ($this->getAiConfig() !== null) {
            return null;
        }

        if ($this->normalisedAiSection() !== null) {
            return 'The ai section is missing api_key. Add it to your phpspec config.';
        }

        return 'AI configuration required. Add an "ai" section to your phpspec config.';
    }

    /**
     * The raw ai section with hyphenated spellings of its keys folded onto the
     * canonical snake_case names (api-key reads as api_key), or null when the
     * config has no ai section. Snake case stays the documented spelling; the
     * common YAML hyphen habit simply keeps working.
     *
     * @return array<string, mixed>|null
     */
    private function normalisedAiSection(): ?array
    {
        $ai = $this->get('ai');
        if (!is_array($ai)) {
            return null;
        }

        foreach (['api-key' => 'api_key', 'max-tokens' => 'max_tokens'] as $hyphenated => $canonical) {
            if (isset($ai[$hyphenated]) && !isset($ai[$canonical])) {
                $ai[$canonical] = $ai[$hyphenated];
            }
        }

        return $ai;
    }
}
