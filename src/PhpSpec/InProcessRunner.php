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

use PhpSpec\Browser\BrowserRegistry;
use PhpSpec\Console\Application;
use PhpSpec\EventDispatcher\DispatcherRegistry;
use PhpSpec\Mock\Double;
use PhpSpec\Mock\Expectation as MockExpectation;
use PhpSpec\StoryBDD\StoryBDDRegistry;
use ReflectionClass;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 * Runs phpspec programmatically within the current process.
 *
 * Saves and restores all static state (Dispatcher, Mock statics, Double cache)
 * so the outer StoryBDD feature runner is not corrupted.
 */
final class InProcessRunner
{
    /**
     * @param string $projectDir absolute path to the temporary project directory
     * @param string $args       CLI arguments string (e.g. 'run', 'run --stop-on-failure')
     * @return InProcessResult   exit code and captured output
     * @throws ClassConflictException when a class from this project is already loaded from a different directory
     */
    public static function run(string $projectDir, string $args = 'run'): InProcessResult
    {
        self::checkForClassConflicts($projectDir);

        // Save outer state
        $savedDispatcher = DispatcherRegistry::dispatcher();
        $savedCwd = getcwd();
        $savedLastDouble = MockExpectation::$lastDouble;
        $savedLastMockReturn = MockExpectation::$lastMockReturn;
        $savedLastCallForAllow = MockExpectation::$lastCallForAllow;
        $savedRegistry = MockExpectation::$registry;
        $savedAutoloaders = spl_autoload_functions();
        $savedStoryBDD = StoryBDDRegistry::saveState();
        $savedBrowser = BrowserRegistry::saveState();

        // Reset for inner run — fresh Dispatcher for the nested Application
        DispatcherRegistry::reset();
        Double::resetCache();
        MockExpectation::$lastDouble = null;
        MockExpectation::$lastMockReturn = null;
        MockExpectation::$lastCallForAllow = null;
        MockExpectation::$registry = [];
        StoryBDDRegistry::init();
        BrowserRegistry::reset();

        $savedCwdStr = is_string($savedCwd) ? $savedCwd : $projectDir;
        chdir($projectDir);

        try {
            $app = new Application('9.0.0');
            $app->setAutoExit(false);
            $app->setCatchExceptions(true);

            $input = new ArrayInput(self::parseArgs($args));
            $input->setInteractive(false);
            $output = new BufferedOutput();
            $exitCode = $app->run($input, $output);

            return new InProcessResult($exitCode, $output->fetch());
        } finally {
            // Always restore, even on error
            chdir($savedCwdStr);
            DispatcherRegistry::set($savedDispatcher);
            MockExpectation::$lastDouble = $savedLastDouble;
            MockExpectation::$lastMockReturn = $savedLastMockReturn;
            MockExpectation::$lastCallForAllow = $savedLastCallForAllow;
            MockExpectation::$registry = $savedRegistry;
            StoryBDDRegistry::restoreState($savedStoryBDD);
            BrowserRegistry::restoreState($savedBrowser);

            // Unregister any autoloaders added by the inner run
            $currentAutoloaders = spl_autoload_functions();
            foreach ($currentAutoloaders as $autoloader) {
                if (!in_array($autoloader, $savedAutoloaders, true)) {
                    spl_autoload_unregister($autoloader);
                }
            }
        }
    }

    /**
     * Checks whether any PHP classes from the project's src/ directory are already
     * loaded from a different directory — indicating a class name conflict across scenarios.
     *
     * @throws ClassConflictException if a conflicting class is found
     */
    private static function checkForClassConflicts(string $projectDir): void
    {
        $srcDir = $projectDir . '/src';
        if (!is_dir($srcDir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($srcDir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($srcDir) + 1);
            $fqcn = str_replace(['/', '.php'], ['\\', ''], $relativePath);

            if (class_exists($fqcn, false) || interface_exists($fqcn, false)) {
                $loaded = (new ReflectionClass($fqcn))->getFileName();
                if ($loaded !== false && !str_starts_with($loaded, $projectDir)) {
                    throw new ClassConflictException("Class $fqcn already loaded from $loaded");
                }
            }
        }
    }

    /**
     * Parses a raw CLI argument string into an ArrayInput-compatible array.
     *
     * Handles the command name, positional arguments (mapped to their
     * Symfony Console argument names), and options with or without values.
     *
     * @param string $args e.g. 'run --stop-on-failure' or 'describe App\Formatter -e add'
     * @return array<string, mixed>
     */
    private static function parseArgs(string $args): array
    {
        $parts = preg_split('/\s+/', trim($args));
        if ($parts === false) {
            return [];
        }
        $result = [];
        $command = null;
        $skipNext = false;

        foreach ($parts as $i => $part) {
            if ($part === '' || $skipNext) {
                $skipNext = false;
                continue;
            }

            if ($command === null) {
                $command = $part;
                $result['command'] = $part;
                continue;
            }

            if (str_starts_with($part, '--')) {
                if (str_contains($part, '=')) {
                    [$key, $value] = explode('=', $part, 2);
                    self::addOptionValue($result, $key, $value);
                } else {
                    // Check if next part is a value (not another option)
                    $next = $parts[$i + 1] ?? null;
                    if ($next !== null && !str_starts_with($next, '-')) {
                        self::addOptionValue($result, $part, $next);
                        $skipNext = true;
                    } else {
                        $result[$part] = true;
                    }
                }
            } elseif (str_starts_with($part, '-') && strlen($part) === 2) {
                // Short option: check if next part is a value
                $next = $parts[$i + 1] ?? null;
                if ($next !== null && !str_starts_with($next, '-')) {
                    self::addOptionValue($result, $part, $next);
                    $skipNext = true;
                } else {
                    $result[$part] = true;
                }
            } else {
                // Positional argument — map to correct argument name
                if ($command === 'run') {
                    if (!isset($result['files']) || !is_array($result['files'])) {
                        $result['files'] = [];
                    }
                    $result['files'][] = $part;
                } elseif ($command === 'describe') {
                    $result['class'] = $part;
                } elseif ($command === 'exemplify') {
                    if (!isset($result['class'])) {
                        $result['class'] = $part;
                    } else {
                        $result['method'] = $part;
                    }
                } elseif ($command === 'refactor') {
                    $result['target'] = $part;
                }
            }
        }

        return $result;
    }

    /**
     * Records an option value, aggregating repeated occurrences into an
     * ordered array so Behat-style --format/-o pairs survive parsing.
     *
     * @param array<string, mixed> $result the parsed arguments (by reference)
     * @param string $key the option name including dashes
     * @param string $value the option value
     */
    private static function addOptionValue(array &$result, string $key, string $value): void
    {
        if (!isset($result[$key])) {
            $result[$key] = $value;

            return;
        }

        if (!is_array($result[$key])) {
            $result[$key] = [$result[$key]];
        }

        $result[$key][] = $value;
    }
}
