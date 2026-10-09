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
use PhpSpec\Coverage\CoverageRegistry;
use PhpSpec\EventDispatcher\DispatcherRegistry;
use PhpSpec\Mock\ArrangingCode;
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
        $savedAutoloaders = spl_autoload_functions();
        $savedStoryBDD = StoryBDDRegistry::saveState();
        $savedBrowser = BrowserRegistry::saveState();
        $savedCoverage = CoverageRegistry::collector();
        $savedArranging = [ArrangingCode::roots(), ArrangingCode::loadedFiles()];
        $savedVerbosity = [getenv('SHELL_VERBOSITY'), $_ENV['SHELL_VERBOSITY'] ?? null, $_SERVER['SHELL_VERBOSITY'] ?? null];

        // Reset for inner run — fresh Dispatcher for the nested Application
        DispatcherRegistry::reset();
        Double::resetCache();
        ArrangingCode::reset();
        MockExpectation::$lastDouble = null;
        MockExpectation::$lastMockReturn = null;
        MockExpectation::$lastCallForAllow = null;
        StoryBDDRegistry::init();
        BrowserRegistry::reset();
        CoverageRegistry::reset();

        $savedCwdStr = is_string($savedCwd) ? $savedCwd : $projectDir;
        chdir($projectDir);

        try {
            $app = new Application('9.0.0', self::argv($args));
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
            StoryBDDRegistry::restoreState($savedStoryBDD);
            BrowserRegistry::restoreState($savedBrowser);
            ArrangingCode::reset();
            ArrangingCode::under(...$savedArranging[0]);
            ArrangingCode::loaded(...$savedArranging[1]);
            self::restoreShellVerbosity($savedVerbosity);

            if ($savedCoverage !== null) {
                CoverageRegistry::activate($savedCoverage);
            } else {
                CoverageRegistry::reset();
            }

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
     * Puts SHELL_VERBOSITY back as it was. A console Application sets it from
     * the options it was run with, and one that ran quiet would otherwise
     * silence every Application run after it in this process.
     *
     * @param array{0: string|false, 1: mixed, 2: mixed} $saved
     */
    private static function restoreShellVerbosity(array $saved): void
    {
        [$env, $dollarEnv, $server] = $saved;

        putenv($env === false ? 'SHELL_VERBOSITY' : 'SHELL_VERBOSITY=' . $env);

        if ($dollarEnv === null) {
            unset($_ENV['SHELL_VERBOSITY']);
        } else {
            $_ENV['SHELL_VERBOSITY'] = $dollarEnv;
        }

        if ($server === null) {
            unset($_SERVER['SHELL_VERBOSITY']);
        } else {
            $_SERVER['SHELL_VERBOSITY'] = $server;
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
     * The command line as a shell would hand it over: split on whitespace,
     * with a single- or double-quoted stretch kept whole and its quotes
     * dropped, so an option value with spaces in it arrives as one argument.
     *
     * @return list<string>
     */
    public static function argv(string $args): array
    {
        preg_match_all('/(?:[^\s"\']+|"[^"]*"|\'[^\']*\')+/', trim($args), $matches);

        return array_map(
            static fn(string $token): string => (string) preg_replace('/"([^"]*)"|\'([^\']*)\'/', '$1$2', $token),
            $matches[0],
        );
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
        $parts = self::argv($args);
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
                } elseif ($command === 'generate') {
                    if (!isset($result['instruction']) || !is_array($result['instruction'])) {
                        $result['instruction'] = [];
                    }
                    $result['instruction'][] = $part;
                } elseif ($command === 'accept') {
                    if (!isset($result['offer']) || !is_array($result['offer'])) {
                        $result['offer'] = [];
                    }
                    $result['offer'][] = $part;
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
