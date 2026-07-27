<?php

use PhpSpec\Ai\Response;
use PhpSpec\Ai\ToolCall;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Next;
use PhpSpec\Console\Command\Pair\SpecRunner;
use PhpSpec\Console\Command\Run\RunOutcome;
use PhpSpec\Console\Command\Run\SuiteSummary;
use PhpSpec\Filesystem;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/../../Ai/ReplayProvider.php';

// A fake SpecRunner that records its argument and returns a scripted outcome, so
// the features-present path can be exercised without spawning a real run.
class NextFakeRunner implements SpecRunner
{
    /** @var list<string> */
    public array $arguments = [];

    public ?RunOutcome $outcome = null;

    public function run(string $argument, OutputInterface $output): ?RunOutcome
    {
        $this->arguments[] = $argument;

        return $this->outcome;
    }
}

describe(Next::class, function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
    });

    it('instantiates', function (Filesystem $fs) {
        $config = new Configuration('.', $fs);
        $cmd = new Next($config, $fs);
        expect($cmd)->toBeAnInstanceOf(Next::class);
    });

    context('command execution', function () {

        it('returns error when AI config is missing', function (Filesystem $fs) {
            $config = new Configuration('.', $fs);
            $cmd = new Next($config, $fs);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute([]);

            expect($exitCode)->toBe(1);
            expect($tester->getDisplay())->toContain('AI configuration required');
        });

        it('displays a spec suggestion with target and reason', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            allow($fs->exists())->toReturnUsing(function (string $path) use ($yamlPath): bool {
                return $path === $yamlPath;
            });
            allow($fs->read())->toReturnUsing(function (string $path) use ($yamlPath): string {
                if ($path === $yamlPath) {
                    return "ai:\n  provider: google\n  api_key: test-key\n";
                }
                return '';
            });

            $configWithAi = new Configuration('.', $fs);
            $suggestFn = fn(array $aiConfig): array => [
                'type' => 'spec',
                'target' => 'App\\UserRepository',
                'reason' => 'The project needs a repository to persist users.',
            ];
            $cmd = new Next($configWithAi, $fs, $suggestFn);

            $app = new Application();
            method_exists($app, 'addCommand') ? $app->addCommand($cmd) : $app->add($cmd);

            $tester = new CommandTester($app->find('next'));
            $tester->setInputs(['n']);
            $exitCode = $tester->execute([]);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('Analysing');
            expect($tester->getDisplay())->toContain('Describe a spec for');
            expect($tester->getDisplay())->toContain('App\\UserRepository');
            expect($tester->getDisplay())->toContain('persist users');
            expect($tester->getDisplay())->toContain('Would you like me to create that for you?');
        });

        it('does not re-describe a spec that already exists, coaching to run instead', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            $specFile = getcwd() . DIRECTORY_SEPARATOR . 'spec' . DIRECTORY_SEPARATOR . 'App' . DIRECTORY_SEPARATOR . 'Existing.spec.php';
            allow($fs->exists())->toReturnUsing(fn(string $path): bool => $path === $yamlPath || $path === $specFile);
            allow($fs->read())->toReturnUsing(function (string $path) use ($yamlPath): string {
                return $path === $yamlPath ? "ai:\n  provider: google\n  api_key: test-key\n" : '';
            });

            $configWithAi = new Configuration('.', $fs);
            $suggestFn = fn(array $aiConfig): array => [
                'type' => 'spec',
                'target' => 'App\\Existing',
                'reason' => 'The class has not been created yet.',
            ];
            $cmd = new Next($configWithAi, $fs, $suggestFn);

            $app = new Application();
            method_exists($app, 'addCommand') ? $app->addCommand($cmd) : $app->add($cmd);

            $tester = new CommandTester($app->find('next'));
            $exitCode = $tester->execute([]);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('already exists');
            expect($tester->getDisplay())->not()->toContain('Would you like me to create');
        });

        it('displays a feature suggestion with confirmation', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            allow($fs->exists())->toReturnUsing(function (string $path) use ($yamlPath): bool {
                return $path === $yamlPath;
            });
            allow($fs->read())->toReturnUsing(function (string $path) use ($yamlPath): string {
                if ($path === $yamlPath) {
                    return "ai:\n  provider: google\n  api_key: test-key\n";
                }
                return '';
            });

            $configWithAi = new Configuration('.', $fs);
            $suggestFn = fn(array $aiConfig): array => [
                'type' => 'feature',
                'target' => 'user registration',
                'reason' => 'No registration flow exists yet.',
            ];
            $cmd = new Next($configWithAi, $fs, $suggestFn);

            $app = new Application();
            method_exists($app, 'addCommand') ? $app->addCommand($cmd) : $app->add($cmd);

            $tester = new CommandTester($app->find('next'));
            $tester->setInputs(['n']);
            $exitCode = $tester->execute([]);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('Write a feature scenario for');
            expect($tester->getDisplay())->toContain('user registration');
            expect($tester->getDisplay())->toContain('Would you like me to create that for you?');
        });

        it('steers to growth instead of re-refactoring a target unchanged since the last refactoring', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            $cwd = getcwd();
            $srcFile = $cwd . '/src/App/TodoList.php';
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath || $p === $srcFile || str_contains($p, 'journal.jsonl'));
            allow($fs->read())->toReturnUsing(function (string $p) use ($yamlPath): string {
                if ($p === $yamlPath) {
                    return "ai:\n  provider: google\n  api_key: test-key\n";
                }
                if (str_contains($p, 'journal.jsonl')) {
                    return '{"at":1000,"command":"refactor","target":"App\\\\TodoList","technique":"Inline Method","description":"x"}' . "\n";
                }
                return '';
            });
            allow($fs->mtime())->toReturn(900);

            $configWithAi = new Configuration('.', $fs);
            $suggestFn = fn(array $aiConfig): array => [
                'type' => 'refactor',
                'target' => 'App\\TodoList',
                'reason' => 'The suite is green.',
            ];
            $cmd = new Next($configWithAi, $fs, $suggestFn);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute([]);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('already refactored');
            expect($tester->getDisplay())->not()->toContain('refactor App/TodoList');
        });

        it('keeps the refactor suggestion when the target changed after the last refactoring', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            $cwd = getcwd();
            $srcFile = $cwd . '/src/App/TodoList.php';
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath || $p === $srcFile || str_contains($p, 'journal.jsonl'));
            allow($fs->read())->toReturnUsing(function (string $p) use ($yamlPath): string {
                if ($p === $yamlPath) {
                    return "ai:\n  provider: google\n  api_key: test-key\n";
                }
                if (str_contains($p, 'journal.jsonl')) {
                    return '{"at":1000,"command":"refactor","target":"App\\\\TodoList","technique":"Inline Method","description":"x"}' . "\n";
                }
                return '';
            });
            allow($fs->mtime())->toReturn(1100);

            $configWithAi = new Configuration('.', $fs);
            $suggestFn = fn(array $aiConfig): array => [
                'type' => 'refactor',
                'target' => 'App\\TodoList',
                'reason' => 'The suite is green.',
            ];
            $cmd = new Next($configWithAi, $fs, $suggestFn);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute([]);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('refactor App/TodoList');
        });

        it('displays a refactor suggestion with the refactor hint', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            allow($fs->exists())->toReturnUsing(function (string $path) use ($yamlPath): bool {
                return $path === $yamlPath;
            });
            allow($fs->read())->toReturnUsing(function (string $path) use ($yamlPath): string {
                if ($path === $yamlPath) {
                    return "ai:\n  provider: google\n  api_key: test-key\n";
                }
                return '';
            });

            $configWithAi = new Configuration('.', $fs);
            $suggestFn = fn(array $aiConfig): array => [
                'type' => 'refactor',
                'target' => 'App\\TodoList',
                'reason' => 'The suite is green and complete() duplicates the lookup.',
            ];
            $cmd = new Next($configWithAi, $fs, $suggestFn);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute([]);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('Refactor');
            expect($tester->getDisplay())->toContain('App\\TodoList');
            expect($tester->getDisplay())->toContain('bin/phpspec refactor App/TodoList');
        });

        it('displays an example suggestion with exemplify hint', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            allow($fs->exists())->toReturnUsing(function (string $path) use ($yamlPath): bool {
                return $path === $yamlPath;
            });
            allow($fs->read())->toReturnUsing(function (string $path) use ($yamlPath): string {
                if ($path === $yamlPath) {
                    return "ai:\n  provider: google\n  api_key: test-key\n";
                }
                return '';
            });

            $configWithAi = new Configuration('.', $fs);
            $suggestFn = fn(array $aiConfig): array => [
                'type' => 'example',
                'target' => 'App\\Calculator',
                'reason' => 'The Calculator spec covers add() but not divide().',
            ];
            $cmd = new Next($configWithAi, $fs, $suggestFn);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute([]);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('Add an example to');
            expect($tester->getDisplay())->toContain('App\\Calculator');
            expect($tester->getDisplay())->toContain('bin/phpspec exemplify');
        });

        it('displays an info suggestion when well-covered', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            allow($fs->exists())->toReturnUsing(function (string $path) use ($yamlPath): bool {
                return $path === $yamlPath;
            });
            allow($fs->read())->toReturnUsing(function (string $path) use ($yamlPath): string {
                if ($path === $yamlPath) {
                    return "ai:\n  provider: google\n  api_key: test-key\n";
                }
                return '';
            });

            $configWithAi = new Configuration('.', $fs);
            $suggestFn = fn(array $aiConfig): array => [
                'type' => 'info',
                'target' => '',
                'reason' => 'Project looks well-covered. Consider adding edge-case scenarios.',
            ];
            $cmd = new Next($configWithAi, $fs, $suggestFn);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute([]);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('well-covered');
        });

        it('shows error when response is unusable', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            allow($fs->exists())->toReturnUsing(function (string $path) use ($yamlPath): bool {
                return $path === $yamlPath;
            });
            allow($fs->read())->toReturnUsing(function (string $path) use ($yamlPath): string {
                if ($path === $yamlPath) {
                    return "ai:\n  provider: google\n  api_key: test-key\n";
                }
                return '';
            });

            $configWithAi = new Configuration('.', $fs);
            $suggestFn = fn(array $aiConfig): array => [
                'type' => 'spec',
                'target' => '',
                'reason' => '',
            ];
            $cmd = new Next($configWithAi, $fs, $suggestFn);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute([]);

            expect($exitCode)->toBe(1);
            expect($tester->getDisplay())->toContain('Could not get a suggestion');
        });

        it('runs the features (--all) and grounds the suggestion in their state when features are present', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            $featuresDir = getcwd() . '/features';
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath || $p === $featuresDir);
            allow($fs->isDir())->toReturnUsing(fn(string $p): bool => $p === $featuresDir);
            allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $yamlPath ? "ai:\n  provider: google\n  api_key: test-key\n" : '');

            $runner = new NextFakeRunner();
            $runner->outcome = new RunOutcome(null, new SuiteSummary(
                'green',
                ['examples' => 0, 'passes' => 0, 'failures' => 0, 'errors' => 0, 'pending' => 0],
                [],
                [],
                ['features' => 1, 'scenarios' => 1, 'steps' => 3, 'stepFailures' => 0, 'undefined' => 3],
                [['path' => 'features/adding.feature', 'status' => 'todo', 'undefined' => 3]],
            ));

            $captured = '';
            $suggestFn = function (array $aiConfig, string $context) use (&$captured): array {
                $captured = $context;

                return ['type' => 'info', 'target' => '', 'reason' => 'ok'];
            };
            $cmd = new Next(new Configuration('.', $fs), $fs, $suggestFn, $runner);

            $tester = new CommandTester($cmd);
            $tester->execute([]);

            expect($runner->arguments)->toContain('--all');
            expect($captured)->toContain('FEATURES');
            expect($captured)->toContain('adding.feature');
        });

        it('does not run the suite when there are no features', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath);
            allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $yamlPath ? "ai:\n  provider: google\n  api_key: test-key\n" : '');

            $runner = new NextFakeRunner();
            $cmd = new Next(new Configuration('.', $fs), $fs, fn(array $aiConfig): array => ['type' => 'info', 'target' => '', 'reason' => 'ok'], $runner);

            $tester = new CommandTester($cmd);
            $tester->execute([]);

            expect($runner->arguments)->toBe([]);
        });

        it('suggests through the agent pipeline when the step needs imagination (features green)', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            $featuresDir = getcwd() . '/features';
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath || $p === $featuresDir);
            allow($fs->isDir())->toReturnUsing(fn(string $p): bool => $p === $featuresDir);
            allow($fs->scandir())->toReturn([]);
            allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $yamlPath ? "ai:\n  provider: google\n  api_key: test-key\n" : '');
            allow($fs->mkdir())->toReturn(null);
            allow($fs->write())->toReturn(null);

            $runner = new NextFakeRunner();
            $runner->outcome = new RunOutcome(null, new SuiteSummary(
                'green',
                ['examples' => 3, 'passes' => 3, 'failures' => 0, 'errors' => 0, 'pending' => 0],
                [],
                [],
                ['features' => 1, 'scenarios' => 1, 'steps' => 3, 'stepFailures' => 0, 'undefined' => 0],
                [['path' => 'features/adding.feature', 'status' => 'green', 'undefined' => 0]],
            ));

            $replay = new ReplayProvider([
                new Response('', [new ToolCall('1', 'suggest_next', ['type' => 'feature', 'target' => 'listing tasks', 'reason' => 'Adding is done; nothing shows the list yet.'])]),
            ]);
            $cmd = new Next(new Configuration('.', $fs), $fs, null, $runner, $replay);

            $app = new Application();
            method_exists($app, 'addCommand') ? $app->addCommand($cmd) : $app->add($cmd);
            $tester = new CommandTester($app->find('next'));
            $tester->setInputs(['n']);
            $tester->execute([]);

            expect($tester->getDisplay())->toContain('Write a feature scenario for');
            expect($tester->getDisplay())->toContain('listing tasks');
            expect($runner->arguments)->toContain('--all');
            expect($replay->requests[0]['messages'][1]->content)->toContain('FEATURES');    // suite grounding reached the model
            expect($replay->requests[0]['messages'][0]->content)->toContain('refactor');    // so did the derived step
        });

        it('suggests the determined step itself, without the model, when the suite determines it', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            $featuresDir = getcwd() . '/features';
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath || $p === $featuresDir);
            allow($fs->isDir())->toReturnUsing(fn(string $p): bool => $p === $featuresDir);
            allow($fs->scandir())->toReturn([]);
            allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $yamlPath ? "ai:\n  provider: google\n  api_key: test-key\n" : '');

            $runner = new NextFakeRunner();
            $runner->outcome = new RunOutcome(null, new SuiteSummary(
                'green',
                ['examples' => 0, 'passes' => 0, 'failures' => 0, 'errors' => 0, 'pending' => 0],
                [],
                [],
                ['features' => 1, 'scenarios' => 1, 'steps' => 3, 'stepFailures' => 0, 'undefined' => 3],
                [['path' => 'features/adding.feature', 'status' => 'todo', 'undefined' => 3]],
            ));

            $replay = new ReplayProvider([new Response('should never be consulted')]);
            $cmd = new Next(new Configuration('.', $fs), $fs, null, $runner, $replay);

            $tester = new CommandTester($cmd);
            $tester->execute([]);

            expect($replay->requests)->toBe([]);                                  // deterministic: no model
            expect($tester->getDisplay())->toContain('undefined steps');
            expect($tester->getDisplay())->toContain('Write the steps.');
        });

        it('passes AI config to the suggest function', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            allow($fs->exists())->toReturnUsing(function (string $path) use ($yamlPath): bool {
                return $path === $yamlPath;
            });
            allow($fs->read())->toReturnUsing(function (string $path) use ($yamlPath): string {
                if ($path === $yamlPath) {
                    return "ai:\n  provider: anthropic\n  api_key: sk-test\n";
                }
                return '';
            });

            $receivedConfig = null;
            $configWithAi = new Configuration('.', $fs);
            $suggestFn = function (array $aiConfig) use (&$receivedConfig): array {
                $receivedConfig = $aiConfig;
                return ['type' => 'info', 'target' => '', 'reason' => 'ok'];
            };
            $cmd = new Next($configWithAi, $fs, $suggestFn);

            $tester = new CommandTester($cmd);
            $tester->execute([]);

            expect($receivedConfig)->not()->toBeNull();
            expect($receivedConfig['provider'])->toBe('anthropic');
            expect($receivedConfig['api_key'])->toBe('sk-test');
        });
    });
});
