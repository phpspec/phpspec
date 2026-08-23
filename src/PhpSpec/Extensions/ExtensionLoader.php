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

namespace PhpSpec\Extensions;

use PhpSpec\Browser\Browser;
use PhpSpec\Configuration;
use PhpSpec\EventDispatcher\DispatcherRegistry;
use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;
use PhpSpec\Specification\Expectation;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 * Reads extension config and auto-discovery, instantiates extensions, and wires them
 * into the appropriate PhpSpec systems (matchers, formatters, commands, listeners, tools).
 */
final class ExtensionLoader
{
    /** @var array<string, FormatterExtension> */
    private array $formatters = [];

    private ?Browser $browser = null;

    /** @var Command[] */
    private array $commands = [];

    /** @var ToolProviderInterface[] */
    private array $toolProviders = [];

    private bool $loaded = false;

    /**
     * @param Configuration   $config     The PhpSpec configuration instance
     * @param Filesystem|null $filesystem Optional filesystem abstraction for testability
     */
    public function __construct(
        private readonly Configuration $config,
        private readonly ?Filesystem $filesystem = null,
    ) {}

    /**
     * Loads and registers all extensions. Safe to call multiple times.
     *
     * @return void
     */
    public function load(): void
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;

        $extensions = $this->config->get('extensions', []);
        if (!is_array($extensions)) {
            return;
        }

        $autoDiscovered = $this->autoDiscover($extensions['disabled'] ?? []);
        $merged = $this->mergeExtensions($extensions, $autoDiscovered);

        foreach ($merged['formatters'] ?? [] as $fqcn) {
            $this->loadFormatter($fqcn);
        }

        foreach ($merged['matchers'] ?? [] as $fqcn) {
            $this->loadMatcher($fqcn);
        }

        foreach ($merged['commands'] ?? [] as $fqcn) {
            $this->loadCommand($fqcn);
        }

        foreach ($merged['listeners'] ?? [] as $fqcn) {
            $this->loadListener($fqcn);
        }

        foreach ($merged['tools'] ?? [] as $fqcn) {
            $this->loadToolProvider($fqcn);
        }

        $this->loadBrowser($extensions, $autoDiscovered);
    }

    /**
     * The browser an extension put behind visit(), or null for the default.
     */
    public function getBrowser(): ?Browser
    {
        return $this->browser;
    }

    /**
     * Returns true if a formatter with the given name is registered.
     *
     * @param string $name The formatter name to look up
     *
     * @return bool
     */
    public function hasFormatter(string $name): bool
    {
        return isset($this->formatters[$name]);
    }

    /**
     * Returns the formatter extension for the given name.
     *
     * @param string $name The formatter name to retrieve
     *
     * @return FormatterExtension
     */
    public function getFormatter(string $name): FormatterExtension
    {
        return $this->formatters[$name];
    }

    /**
     * Returns all extension commands wrapped as Symfony Commands.
     *
     * @return Command[]
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Returns all tool providers.
     *
     * @return ToolProviderInterface[]
     */
    public function getToolProviders(): array
    {
        return $this->toolProviders;
    }

    private function loadFormatter(string $fqcn): void
    {
        if (!class_exists($fqcn)) {
            return;
        }
        $instance = new $fqcn();
        if ($instance instanceof FormatterExtension) {
            $this->formatters[$instance->getName()] = $instance;
        }
    }

    private function loadMatcher(string $fqcn): void
    {
        if (!class_exists($fqcn)) {
            return;
        }
        $instance = new $fqcn();
        if ($instance instanceof MatcherExtension) {
            Expectation::addMatcher(
                $instance->getName(),
                fn(mixed $actual, mixed ...$args) => $instance->match($actual, ...$args),
                fn(mixed $actual) => $instance->failureMessage($actual),
            );
        }
    }

    private function loadCommand(string $fqcn): void
    {
        if (!class_exists($fqcn)) {
            return;
        }
        $ext = new $fqcn();
        if (!$ext instanceof CommandExtension) {
            return;
        }

        $cmd = new class ($ext) extends Command {
            public function __construct(private readonly CommandExtension $ext)
            {
                parent::__construct($ext->getName());
            }

            protected function configure(): void
            {
                $this->setDescription($this->ext->getDescription());
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return $this->ext->execute($input, $output);
            }
        };

        $this->commands[] = $cmd;
    }

    private function loadListener(string $fqcn): void
    {
        if (!class_exists($fqcn)) {
            return;
        }
        $instance = new $fqcn();
        if ($instance instanceof ListenerExtension) {
            DispatcherRegistry::dispatcher()->addSubscriber(new ListenerBridge($instance));
        }
    }

    private function loadToolProvider(string $fqcn): void
    {
        if (!class_exists($fqcn)) {
            return;
        }
        $instance = new $fqcn();
        if ($instance instanceof ToolProviderInterface) {
            $this->toolProviders[] = $instance;
        }
    }

    /**
     * Exactly one browser can drive the DSL, so the config's word is final and
     * an auto-discovery tie is refused rather than settled by install order.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $discovered
     */
    private function loadBrowser(array $config, array $discovered): void
    {
        $configured = $config['browser'] ?? null;
        $found = array_values(array_unique(array_filter((array) ($discovered['browser'] ?? []), 'is_string')));

        if (!is_string($configured) && count($found) > 1) {
            throw new RuntimeException(sprintf(
                'Two browsers were auto-discovered ("%s" and "%s"): pick one with "extensions: {browser: ...}".',
                self::shortName($found[0]),
                self::shortName($found[1]),
            ));
        }

        $fqcn = is_string($configured) ? $configured : ($found[0] ?? null);
        if ($fqcn === null || !class_exists($fqcn)) {
            return;
        }

        $instance = new $fqcn();
        if (!$instance instanceof Browser) {
            throw new RuntimeException(sprintf(
                'The configured browser "%s" must implement PhpSpec\Browser\Browser.',
                self::shortName($fqcn),
            ));
        }

        $this->browser = $instance;
    }

    private static function shortName(string $fqcn): string
    {
        $slash = strrpos($fqcn, '\\');

        if ($slash === false) {
            return $fqcn;
        }

        return substr($fqcn, $slash + 1);
    }

    /**
     * Reads vendor/composer/installed.json for auto-discovery.
     *
     * @param array<string> $disabled package names to skip
     * @return array<string, string[]> type => FQCNs
     */
    private function autoDiscover(array $disabled): array
    {
        $fs = $this->filesystem ?? new RealFilesystem();
        $installedPath = 'vendor/composer/installed.json';
        if (!$fs->exists($installedPath)) {
            return [];
        }

        $content = $fs->read($installedPath);
        $data = json_decode($content, true);
        if (!is_array($data)) {
            return [];
        }

        // Composer 2 format: {packages: [...]}
        $packages = $data['packages'] ?? $data;
        if (!is_array($packages)) {
            return [];
        }

        $result = [];
        foreach ($packages as $package) {
            if (!is_array($package)) {
                continue;
            }
            $name = $package['name'] ?? '';
            if (in_array($name, $disabled, true)) {
                continue;
            }
            $phpspec = $package['extra']['phpspec'] ?? null;
            if (!is_array($phpspec)) {
                continue;
            }
            foreach (['formatters', 'matchers', 'commands', 'listeners', 'tools'] as $type) {
                if (isset($phpspec[$type]) && is_array($phpspec[$type])) {
                    $result[$type] = array_merge($result[$type] ?? [], $phpspec[$type]);
                }
            }
            if (isset($phpspec['browser']) && is_string($phpspec['browser'])) {
                $result['browser'][] = $phpspec['browser'];
            }
        }

        return $result;
    }

    /**
     * Merges config extensions with auto-discovered ones, deduplicating.
     *
     * @param array<string, mixed> $config
     * @param array<string, string[]> $discovered
     * @return array<string, string[]>
     */
    private function mergeExtensions(array $config, array $discovered): array
    {
        $result = [];
        foreach (['formatters', 'matchers', 'commands', 'listeners', 'tools'] as $type) {
            $configItems = $config[$type] ?? [];
            $discoveredItems = $discovered[$type] ?? [];
            if (!is_array($configItems)) {
                $configItems = [];
            }
            $result[$type] = array_unique(array_merge($configItems, $discoveredItems));
        }
        return $result;
    }
}
