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

use BackedEnum;
use InvalidArgumentException;
use PhpSpec\Ai\Contracts\ProviderCatalogueInterface;
use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\Papi\PapiCatalogue;
use PhpSpec\CodeGeneration\SourceLayout;
use PhpSpec\Coverage\CoverageAdapter;
use PhpSpec\Source\ComposerAutoload;
use PhpSpec\Source\ComposerJson;
use PhpSpec\Source\ProjectAutoloader;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * A project's configuration: its settings, the root its paths are relative
 * to, and the autoloading its composer.json declares, when it has one. Built
 * from values it touches no disk; {@see load()} reads what a project states.
 */
final class Configuration
{
    /** @var array<string, 'bool'|'string'|'array'|'list'|class-string<BackedEnum>> every key a config file may state, and the kind of value it takes: a scalar kind, a map, a list of strings, or the enum of the values it may hold */
    private const KNOWN_KEYS = [
        'spec_path' => 'string',
        'src_path' => 'string',
        'spec_suffix' => 'string',
        'features_path' => 'string',
        'steps_path' => 'string',
        'format' => 'string',
        'bootstrap' => 'string',
        'base_url' => 'string',
        'default_namespace' => 'string',
        'coverage_adapter' => CoverageAdapter::class,
        'coverage_exclude' => 'list',
        'stop_on_failure' => 'bool',
        'stop_on_error' => 'bool',
        'stop_on_warning' => 'bool',
        'stop_on_notice' => 'bool',
        'stop_on_deprecation' => 'bool',
        'stop_on_pending' => 'bool',
        'stop_on_skipped' => 'bool',
        'autoload' => 'array',
        'suites' => 'array',
        'extensions' => 'array',
        'ai' => 'array',
        'guard' => 'array',
    ];
    /**
     * @param array<string, mixed> $config the settings, as a config file states them
     * @param string $rootDir the project root the paths are relative to
     * @param ComposerAutoload|null $composer what composer.json declares, or null for a project without one
     * @param string|null $settingsFile the file the settings were read from, when there is one
     * @param bool $named whether the settings file was named with --config rather than found under the root
     * @param ProviderCatalogueInterface|null $providers the AI providers the project has installed; papi's when not given
     */
    public function __construct(
        private array $config = [],
        private string $rootDir = '.',
        private ?ComposerAutoload $composer = null,
        private ?string $settingsFile = null,
        private bool $named = false,
        private ?ProviderCatalogueInterface $providers = null,
    ) {}

    /**
     * What a project states: phpspec.yaml, phpspec.yml, phpspec.json or
     * phpspec.php under the root, the first found, or the file --config names;
     * and the autoloading of its composer.json, which the settings must leave
     * to it.
     *
     * @param string $rootDir project root directory containing the config files
     * @param Filesystem|null $filesystem the filesystem to read through
     * @param string|null $configFile explicit config file path; when set, the working directory cascade is skipped
     * @param ProviderCatalogueInterface|null $providers the AI providers the project has installed; papi's when not given
     */
    public static function load(string $rootDir = '.', ?Filesystem $filesystem = null, ?string $configFile = null, ?ProviderCatalogueInterface $providers = null): self
    {
        $fs = $filesystem ?? new RealFilesystem();
        $path = $configFile ?? self::settingsFile($rootDir, $fs);
        $settings = $path === null ? [] : self::readFile($path, $fs);
        $configuration = new self($settings, $rootDir, ComposerAutoload::of($rootDir, $fs), $path, $configFile !== null, $providers);
        $configuration->checkAgainstComposer();

        return $configuration;
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
     * The config file a project keeps under its root, the first found of
     * phpspec.yaml, phpspec.yml, phpspec.json and phpspec.php, or null.
     */
    private static function settingsFile(string $rootDir, Filesystem $fs): ?string
    {
        foreach (['phpspec.yaml', 'phpspec.yml', 'phpspec.json', 'phpspec.php'] as $name) {
            $path = $rootDir . '/' . $name;

            if ($fs->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * The settings a file states, checked key by key: a file that does not
     * parse, a key one of PhpSpec's own is near (a typo, read by nothing) or
     * a value of the wrong kind is refused naming the file, since taking it
     * silently would run something other than what was written. A key none
     * of PhpSpec's own is near is the project's, read through get().
     *
     * @return array<string, mixed>
     */
    private static function readFile(string $path, Filesystem $fs): array
    {
        if (!$fs->exists($path)) {
            throw new ConfigurationException("Configuration file not found: $path");
        }

        try {
            $settings = match (pathinfo($path, PATHINFO_EXTENSION)) {
                'yaml', 'yml' => Yaml::parse($fs->read($path)),
                'json' => json_decode($fs->read($path), true, 512, JSON_THROW_ON_ERROR),
                'php' => $fs->requirePhp($path),
                default => throw new ConfigurationException("Unsupported configuration file type: $path"),
            };
        } catch (ConfigurationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ConfigurationException(sprintf('%s could not be read: %s', $path, $e->getMessage()), 0, $e);
        }

        if ($settings === null) {
            return [];
        }

        if (!is_array($settings)) {
            throw new ConfigurationException(sprintf('%s does not hold a map of settings.', $path));
        }

        foreach ($settings as $key => $value) {
            self::checkSetting($path, (string) $key, $value);
        }

        self::checkNoNamespaceMapping($path, $settings);

        return $settings;
    }

    /**
     * Refuses a key that would map a namespace to a directory, psr4_prefix or
     * a suite's namespace: composer.json does that, or autoload without one.
     *
     * @param array<mixed> $settings
     */
    private static function checkNoNamespaceMapping(string $path, array $settings): void
    {
        $keys = array_key_exists('psr4_prefix', $settings) ? ['psr4_prefix'] : [];

        foreach (is_array($settings['suites'] ?? null) ? $settings['suites'] : [] as $name => $suite) {
            if (is_array($suite) && array_key_exists('namespace', $suite)) {
                $keys[] = 'suites.' . $name . '.namespace';
            }
        }

        if ($keys !== []) {
            throw new ConfigurationException(sprintf('%s: %s is not a setting: composer.json maps namespaces to directories, or autoload does in a project without one.', $path, $keys[0]));
        }
    }

    private static function checkSetting(string $path, string $key, mixed $value): void
    {
        $expected = self::KNOWN_KEYS[$key] ?? null;

        if ($expected === null) {
            $nearest = self::nearestOf($key, array_keys(self::KNOWN_KEYS));

            if ($nearest !== null) {
                throw new ConfigurationException(sprintf('%s: unknown key "%s". Did you mean "%s"?', $path, $key, $nearest));
            }

            return;
        }

        $wellTyped = match ($expected) {
            'bool' => is_bool($value),
            'string' => is_string($value),
            'array' => is_array($value),
            'list' => is_array($value) && array_is_list($value) && array_filter($value, static fn(mixed $item): bool => !is_string($item) || $item === '') === [],
            default => is_string($value) && $expected::tryFrom($value) !== null,
        };

        if ($wellTyped) {
            return;
        }

        throw new ConfigurationException(sprintf(
            '%s: %s expects %s, %s given.',
            $path,
            $key,
            match ($expected) {
                'bool' => 'true or false',
                'string' => 'a string',
                'array' => 'a map',
                'list' => 'a list of paths and names',
                default => implode(' or ', array_map(static fn(BackedEnum $case): string => (string) $case->value, $expected::cases())),
            },
            is_scalar($value) ? '"' . $value . '"' : get_debug_type($value),
        ));
    }

    /**
     * Refuses settings that would autoload beside Composer: an autoload map;
     * a source path, the top-level one or a suite's, that is not a directory
     * composer.json maps a namespace to; or a default namespace no mapping
     * places whose classes the source path cannot take.
     */
    private function checkAgainstComposer(): void
    {
        if ($this->settingsFile === null || $this->composer === null) {
            return;
        }

        if (array_key_exists('autoload', $this->config)) {
            throw new ConfigurationException(sprintf('%s: autoload cannot be set in a project with a composer.json.', $this->settingsFile));
        }

        if ($this->composer->directories() === []) {
            return;
        }

        $sourcePaths = ['src_path' => $this->config['src_path'] ?? null];

        foreach (is_array($this->config['suites'] ?? null) ? $this->config['suites'] : [] as $name => $suite) {
            $sourcePaths['suites.' . $name . '.src'] = is_array($suite) ? ($suite['src'] ?? null) : null;
        }

        foreach ($sourcePaths as $key => $sourcePath) {
            if (is_string($sourcePath) && !$this->composer->mapsTo($sourcePath)) {
                throw new ConfigurationException(sprintf(
                    '%s: %s "%s" is not a directory composer.json maps a namespace to: %s.',
                    $this->settingsFile,
                    $key,
                    $sourcePath,
                    implode(', ', $this->composer->directories()),
                ));
            }
        }

        $default = trim((string) $this->get('default_namespace', ''), '\\');
        $claim = $default === '' || $this->srcDir() === '' || $this->composer->places($default) ? null : $this->composer->claimOn($this->srcDir());

        if ($claim !== null) {
            throw new ConfigurationException(sprintf(
                '%s: default_namespace "%s" is under no namespace composer.json maps, and %s/ cannot take its classes: %s.',
                $this->settingsFile,
                $default,
                $this->srcDir(),
                $claim,
            ));
        }
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
     * Returns the source directory, where a class under no mapping is
     * generated: the one the config states, else `src`.
     */
    public function getSrcPath(): string
    {
        $srcPath = $this->config['src_path'] ?? $this->suiteValue('src');

        return is_string($srcPath) ? $srcPath : './src';
    }

    /**
     * Every directory the project's classes are in, relative to the project
     * root, as a walk through the source takes them: the source directory
     * and each mapped one, once each, none inside another.
     *
     * @return list<string>
     */
    public function sourceRoots(): array
    {
        return $this->getSourceLayout()->roots();
    }

    /**
     * The source directory relative to the project root, as code joins and
     * compares it: no leading "./", no trailing slash.
     */
    public function srcDir(): string
    {
        return $this->projectDir($this->getSrcPath());
    }

    /**
     * The spec directory relative to the project root, as code joins and
     * compares it: no leading "./", no trailing slash.
     */
    public function specDir(): string
    {
        return $this->projectDir($this->getSpecPath());
    }

    /**
     * Where a class's source file lives, mapping by mapping: the ones
     * composer.json declares or, in a project without one, the `autoload` map
     * the config states. A class under no mapping goes below `src_path`, `src`
     * by default, and into `default_namespace` first when the config names one.
     */
    public function getSourceLayout(): SourceLayout
    {
        $srcPath = $this->config['src_path'] ?? $this->suiteValue('src');
        $default = $this->get('default_namespace');

        return new SourceLayout(
            $this->srcDir(),
            $this->mappings(),
            is_string($default) && trim($default, '\\') !== '' ? $default : null,
        );
    }

    /**
     * @return array<string, string>
     */
    private function mappings(): array
    {
        if ($this->composer !== null) {
            return $this->composer->mappings();
        }

        $autoload = $this->config['autoload'] ?? null;
        $mappings = [];

        foreach (is_array($autoload) ? $autoload : [] as $prefix => $directory) {
            if (is_string($prefix) && is_string($directory)) {
                $mappings[$prefix] = $this->projectDir($directory);
            }
        }

        return $mappings;
    }

    private function projectDir(string $path): string
    {
        $dir = rtrim((string) preg_replace('~^(\./)+~', '', str_replace('\\', '/', $path)), '/');

        return $dir === '.' ? '' : $dir;
    }

    /**
     * The first suite's value for a key, or null.
     */
    private function suiteValue(string $key): mixed
    {
        $suites = $this->config['suites'] ?? null;

        if (!is_array($suites)) {
            return null;
        }

        foreach ($suites as $suite) {
            if (is_array($suite) && isset($suite[$key])) {
                return $suite[$key];
            }
        }

        return null;
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
     * Returns whether to stop on pending examples from configuration.
     */
    public function getStopOnPending(): bool
    {
        return $this->get('stop_on_pending', false);
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
            onPending: $this->getStopOnPending(),
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
    /** The ai keys phpspec reads itself; every other one is a setting of the provider. */
    private const AI_KEYS = ['provider', 'model', 'max_tokens', 'effort'];

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
     * Whether guard judges runs: a usable guard section that makes it active.
     */
    public function guardIsOn(): bool
    {
        return ($this->getGuardConfig()['status'] ?? 'off') === 'active';
    }

    /**
     * The options a phpspec started from this one needs to read the same
     * configuration: the file --config named, when one was. A file found under
     * the root is found again by a phpspec started from the same directory.
     *
     * @return list<string>
     */
    public function childOptions(): array
    {
        return $this->named && $this->settingsFile !== null ? ['--config=' . $this->settingsFile] : [];
    }

    /**
     * The paths and names coverage leaves out, as the config lists them.
     *
     * @return list<string>
     */
    public function coverageExclusions(): array
    {
        $entries = $this->config['coverage_exclude'] ?? [];

        return is_array($entries) ? array_values(array_filter($entries, 'is_string')) : [];
    }

    /**
     * The coverage engine the project named, or null when the run takes
     * whichever is enabled.
     */
    public function coverageAdapter(): ?CoverageAdapter
    {
        $named = $this->config['coverage_adapter'] ?? null;

        return is_string($named) ? CoverageAdapter::from($named) : null;
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
        return ucfirst(self::unknownKey($key, array_keys(self::GUARD_DEFAULTS), 'guard key'));
    }

    /**
     * A key nothing reads, with the known key it most likely meant when one
     * is near, else all of them.
     *
     * @param list<string> $known
     */
    private static function unknownKey(string $key, array $known, string $what): string
    {
        $closest = self::nearestOf($key, $known);

        if ($closest !== null) {
            return sprintf('unknown %s "%s". Did you mean "%s"?', $what, $key, $closest);
        }

        return sprintf('unknown %s "%s". The known keys are %s.', $what, $key, self::naturalList($known));
    }

    /**
     * The known key a few letters away from the given one, or null when none is.
     *
     * @param list<string> $known
     */
    private static function nearestOf(string $key, array $known): ?string
    {
        $closest = null;
        $best = 4;
        foreach ($known as $candidate) {
            $distance = levenshtein($key, $candidate);
            if ($distance < $best) {
                $best = $distance;
                $closest = $candidate;
            }
        }

        return $closest;
    }

    /**
     * Returns what phpspec reads from the AI configuration block, or null
     * when it is absent or unusable ({@see aiConfigProblem()} says why).
     *
     * @return array{provider: string, model?: string, maxTokens?: int, effort?: string}|null
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

        return $result;
    }

    /**
     * The provider the ai section names, built with the provider's own
     * settings: every key of the section phpspec does not read itself.
     *
     * @throws RuntimeException when the ai section is missing or unusable
     * @throws InvalidArgumentException when the provider is not installed, or refuses its settings
     */
    public function aiProvider(): ProviderInterface
    {
        $ai = $this->getAiConfig() ?? throw new RuntimeException((string) $this->aiConfigProblem());

        return $this->providers()->create($ai['provider'], array_diff_key($this->normalisedAiSection() ?? [], array_flip(self::AI_KEYS)));
    }

    /**
     * The project's composer.json, to change, or null for a project without one.
     */
    public function composerJson(Filesystem $filesystem = new RealFilesystem()): ?ComposerJson
    {
        return $this->composer === null ? null : new ComposerJson(rtrim($this->rootDir, '/') . '/composer.json', $filesystem);
    }

    /**
     * Registers the autoloader of the project's classes, loading each from
     * where the source layout puts it.
     */
    public function registerAutoloaders(): void
    {
        spl_autoload_register(new ProjectAutoloader($this->getSourceLayout(), rtrim($this->rootDir, '/')));
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
        $refusal = $this->aiConfigRefusal();

        if ($refusal === null || $refusal['remedy'] === null) {
            return $refusal['problem'] ?? null;
        }

        return sprintf("%s, for example:\n\n%s", rtrim($refusal['problem'], '.'), $refusal['remedy']);
    }

    /**
     * The same as {@see aiConfigProblem()}, the problem apart from the
     * config that would answer it, for a reader that keeps them apart.
     *
     * @return array{problem: string, remedy: string|null}|null
     */
    public function aiConfigRefusal(): ?array
    {
        $ai = $this->normalisedAiSection();
        if ($ai === null) {
            return $this->missingAiSection();
        }

        $gap = $this->aiSectionGap($ai);

        return $gap === null ? null : ['problem' => $gap, 'remedy' => null];
    }

    /**
     * Which file to put the ai section in, and what one looks like in that
     * file's own notation, with the installed provider when there is one.
     *
     * @return array{problem: string, remedy: string}
     */
    private function missingAiSection(): array
    {
        $installed = array_keys($this->installedOrNone());
        $provider = count($installed) === 1 ? $installed[0] : 'anthropic';
        $file = $this->settingsFile ?? $this->rootDir . '/phpspec.yml';
        $shown = str_starts_with($file, $this->rootDir . '/') ? substr($file, strlen($this->rootDir) + 1) : $file;

        $example = match (pathinfo($file, PATHINFO_EXTENSION)) {
            'json' => sprintf('"ai": {"provider": "%s", "api_key": "YOUR_API_KEY"}', $provider),
            'php' => sprintf("'ai' => ['provider' => '%s', 'api_key' => 'YOUR_API_KEY'],", $provider),
            default => sprintf("ai:\n  provider: %s\n  api_key: YOUR_API_KEY", $provider),
        };

        return [
            'problem' => sprintf(
                'AI configuration required. %s an "ai" section%s.',
                $this->settingsFile === null ? 'Create ' . $shown . ' with' : 'Add',
                $this->settingsFile === null ? '' : ' to ' . $shown,
            ),
            'remedy' => $example,
        ];
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
     * or null when the section is usable. The reader and the diagnosis both
     * derive from it, so they cannot disagree. Only the keys phpspec reads
     * are judged here; the provider judges its own settings when it is built.
     *
     * @param array<string, mixed> $ai the normalised ai section
     */
    private function aiSectionGap(array $ai): ?string
    {
        if (!isset($ai['provider'])) {
            return $this->missingProvider();
        }

        if (!is_string($ai['provider']) || $ai['provider'] === '') {
            return 'The ai section\'s provider must be a provider name.';
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

        return null;
    }

    /**
     * The missing-provider message, naming what the project has installed.
     */
    private function missingProvider(): string
    {
        try {
            $installed = $this->providers()->installed();
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }

        if ($installed === []) {
            return 'The ai section is missing provider, and no papi provider package is installed.';
        }

        if (count($installed) === 1) {
            return sprintf('The ai section is missing provider. %s is installed, so set provider: %s.', current($installed), key($installed));
        }

        return sprintf('The ai section is missing provider. Set it to %s.', self::naturalList(array_keys($installed), 'or'));
    }

    /**
     * @return array<string, string>
     */
    private function installedOrNone(): array
    {
        try {
            return $this->providers()->installed();
        } catch (InvalidArgumentException) {
            return [];
        }
    }

    private function providers(): ProviderCatalogueInterface
    {
        return $this->providers ??= new PapiCatalogue();
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
     * canonical snake_case names (api-key reads as api_key), in the order
     * written, or null when the config has no ai section. Snake case stays the
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

        $normalised = [];
        foreach ($ai as $key => $value) {
            $canonical = str_replace('-', '_', (string) $key);
            $normalised[isset($ai[$canonical]) ? (string) $key : $canonical] = $value;
        }

        return $normalised;
    }
}
