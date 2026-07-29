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
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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

        it('actuates an accepted feature suggestion through generate, never describe', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath);
            allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $yamlPath ? "ai:\n  provider: google\n  api_key: test-key\n" : '');

            $configWithAi = new Configuration('.', $fs);
            $suggestFn = fn(array $aiConfig): array => [
                'type' => 'feature',
                'target' => 'deleting_a_task',
                'reason' => 'The suite is green; grow the story.',
            ];
            $cmd = new Next($configWithAi, $fs, $suggestFn);

            $fakeGenerate = new class extends SymfonyCommand {
                public string $received = '';
                protected function configure(): void
                {
                    $this->setName('generate')->addArgument('instruction', InputArgument::IS_ARRAY);
                }
                protected function execute(InputInterface $input, OutputInterface $output): int
                {
                    $this->received = implode(' ', (array) $input->getArgument('instruction'));
                    $output->writeln('feature written');

                    return 0;
                }
            };
            $fakeDescribe = new class extends SymfonyCommand {
                protected function configure(): void
                {
                    $this->setName('describe')->addArgument('class', InputArgument::REQUIRED);
                }
                protected function execute(InputInterface $input, OutputInterface $output): int
                {
                    $output->writeln('Specification created');

                    return 0;
                }
            };

            $app = new Application();
            foreach ([$cmd, $fakeGenerate, $fakeDescribe] as $command) {
                method_exists($app, 'addCommand') ? $app->addCommand($command) : $app->add($command);
            }

            $tester = new CommandTester($app->find('next'));
            $tester->setInputs(['Y']);
            $exitCode = $tester->execute([]);

            expect($exitCode)->toBe(0);
            expect($fakeGenerate->received)->toBe('a feature for deleting_a_task');
            expect($tester->getDisplay())->toContain('feature written');
            expect($tester->getDisplay())->not()->toContain('Specification');
        });

        it('hints generate for a feature suggestion when not interactive', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath);
            allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $yamlPath ? "ai:\n  provider: google\n  api_key: test-key\n" : '');

            $configWithAi = new Configuration('.', $fs);
            $suggestFn = fn(array $aiConfig): array => [
                'type' => 'feature',
                'target' => 'deleting_a_task',
                'reason' => 'The suite is green; grow the story.',
            ];
            $cmd = new Next($configWithAi, $fs, $suggestFn);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute([], ['interactive' => false]);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('generate "a feature for deleting_a_task"');
            expect($tester->getDisplay())->not()->toContain('describe');
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
            $exitCode = $tester->execute([], ['interactive' => false]);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('refactor App/TodoList');
        });

        it('keeps a refactor suggestion for a class the journal never recorded', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            $cwd = getcwd();
            $srcFile = $cwd . '/src/App/TaskQueue.php';
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
                'target' => 'App\\TaskQueue',
                'reason' => 'The suite is green.',
            ];
            $cmd = new Next($configWithAi, $fs, $suggestFn);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute([], ['interactive' => false]);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('refactor App/TaskQueue');
        });

        it('tells the model which classes are already polished, so it proposes growth itself', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            $cwd = getcwd();
            $featuresDir = $cwd . '/features';
            $srcFile = $cwd . '/src/App/TodoList.php';
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath || $p === $featuresDir || $p === $srcFile || str_contains($p, 'journal.jsonl'));
            allow($fs->isDir())->toReturnUsing(fn(string $p): bool => $p === $featuresDir);
            allow($fs->scandir())->toReturn([]);
            allow($fs->read())->toReturnUsing(function (string $p) use ($yamlPath): string {
                if ($p === $yamlPath) {
                    return "ai:\n  provider: google\n  api_key: test-key\n";
                }
                if (str_contains($p, 'journal.jsonl')) {
                    return '{"at":1000,"command":"refactor","target":"App\\\\TodoList","technique":"Extract Method","description":"Pulled hasTask out"}' . "\n";
                }
                return '';
            });
            allow($fs->mtime())->toReturn(900);
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
                new Response('', [new ToolCall('1', 'suggest_next', ['type' => 'feature', 'target' => 'clearing completed tasks', 'reason' => 'The list only grows; nothing clears what is done.'])]),
            ]);
            $cmd = new Next(new Configuration('.', $fs), $fs, null, $runner, $replay);

            $app = new Application();
            method_exists($app, 'addCommand') ? $app->addCommand($cmd) : $app->add($cmd);
            $tester = new CommandTester($app->find('next'));
            $tester->setInputs(['n']);
            $tester->execute([]);

            expect($replay->requests[0]['messages'][0]->content)->toContain('journal');
            expect($replay->requests[0]['messages'][1]->content)->toContain('Already refactored and unchanged since: App\\TodoList');
            expect($tester->getDisplay())->toContain('Write a feature scenario for');
            expect($tester->getDisplay())->toContain('clearing completed tasks');
        });

        it('actuates an accepted refactor suggestion by running the refactor command', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath);
            allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $yamlPath ? "ai:\n  provider: google\n  api_key: test-key\n" : '');

            $configWithAi = new Configuration('.', $fs);
            $suggestFn = fn(array $aiConfig): array => [
                'type' => 'refactor',
                'target' => 'App\\TodoList',
                'reason' => 'The suite is green and complete() duplicates the lookup.',
            ];
            $cmd = new Next($configWithAi, $fs, $suggestFn);

            $fakeRefactor = new class extends SymfonyCommand {
                public string $received = '';
                protected function configure(): void
                {
                    $this->setName('refactor')->addArgument('target', InputArgument::REQUIRED);
                }
                protected function execute(InputInterface $input, OutputInterface $output): int
                {
                    $this->received = (string) $input->getArgument('target');

                    return 0;
                }
            };

            $app = new Application();
            foreach ([$cmd, $fakeRefactor] as $command) {
                method_exists($app, 'addCommand') ? $app->addCommand($command) : $app->add($command);
            }

            $tester = new CommandTester($app->find('next'));
            $tester->setInputs(['Y']);
            $exitCode = $tester->execute([]);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('Refactor');
            expect($tester->getDisplay())->toContain('Would you like me to run it now?');
            expect($fakeRefactor->received)->toBe('App/TodoList');
        });

        it('hints the refactor command when not interactive', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath);
            allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $yamlPath ? "ai:\n  provider: google\n  api_key: test-key\n" : '');

            $configWithAi = new Configuration('.', $fs);
            $suggestFn = fn(array $aiConfig): array => [
                'type' => 'refactor',
                'target' => 'App\\TodoList',
                'reason' => 'The suite is green and complete() duplicates the lookup.',
            ];
            $cmd = new Next($configWithAi, $fs, $suggestFn);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute([], ['interactive' => false]);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('Refactor');
            expect($tester->getDisplay())->toContain('refactor App/TodoList');
        });

        it('actuates an accepted example suggestion through generate', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath);
            allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $yamlPath ? "ai:\n  provider: google\n  api_key: test-key\n" : '');

            $configWithAi = new Configuration('.', $fs);
            $suggestFn = fn(array $aiConfig): array => [
                'type' => 'example',
                'target' => 'App\\Calculator',
                'reason' => 'The Calculator spec covers add() but not divide().',
            ];
            $cmd = new Next($configWithAi, $fs, $suggestFn);

            $fakeGenerate = new class extends SymfonyCommand {
                public string $received = '';
                protected function configure(): void
                {
                    $this->setName('generate')->addArgument('instruction', InputArgument::IS_ARRAY);
                }
                protected function execute(InputInterface $input, OutputInterface $output): int
                {
                    $this->received = implode(' ', (array) $input->getArgument('instruction'));

                    return 0;
                }
            };

            $app = new Application();
            foreach ([$cmd, $fakeGenerate] as $command) {
                method_exists($app, 'addCommand') ? $app->addCommand($command) : $app->add($command);
            }

            $tester = new CommandTester($app->find('next'));
            $tester->setInputs(['Y']);
            $exitCode = $tester->execute([]);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('Add an example to');
            expect($tester->getDisplay())->toContain('App\\Calculator');
            expect($fakeGenerate->received)->toBe('add a spec example for App\\Calculator: The Calculator spec covers add() but not divide().');
        });

        it('strips file tokens from the reason it forwards, so routing stays on the spec', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath);
            allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $yamlPath ? "ai:\n  provider: google\n  api_key: test-key\n" : '');

            $configWithAi = new Configuration('.', $fs);
            $suggestFn = fn(array $aiConfig): array => [
                'type' => 'example',
                'target' => 'App\\TodoList',
                'reason' => 'Implement the pending scenario in features/clearing_completed_tasks.feature now.',
            ];
            $cmd = new Next($configWithAi, $fs, $suggestFn);

            $fakeGenerate = new class extends SymfonyCommand {
                public string $received = '';
                protected function configure(): void
                {
                    $this->setName('generate')->addArgument('instruction', InputArgument::IS_ARRAY);
                }
                protected function execute(InputInterface $input, OutputInterface $output): int
                {
                    $this->received = implode(' ', (array) $input->getArgument('instruction'));

                    return 0;
                }
            };

            $app = new Application();
            foreach ([$cmd, $fakeGenerate] as $command) {
                method_exists($app, 'addCommand') ? $app->addCommand($command) : $app->add($command);
            }

            $tester = new CommandTester($app->find('next'));
            $tester->setInputs(['Y']);
            $tester->execute([]);

            expect($fakeGenerate->received)->toContain('add a spec example for App\\TodoList');
            expect($fakeGenerate->received)->toContain('pending scenario');
            expect($fakeGenerate->received)->not()->toContain('.feature');
        });

        it('hints the example generate line when not interactive', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath);
            allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $yamlPath ? "ai:\n  provider: google\n  api_key: test-key\n" : '');

            $configWithAi = new Configuration('.', $fs);
            $suggestFn = fn(array $aiConfig): array => [
                'type' => 'example',
                'target' => 'App\\Calculator',
                'reason' => 'The Calculator spec covers add() but not divide().',
            ];
            $cmd = new Next($configWithAi, $fs, $suggestFn);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute([], ['interactive' => false]);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('generate "add a spec example for App\\Calculator');
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
            allow($fs->scandir())->toReturnUsing(fn(string $d): array => $d === $featuresDir ? ['adding.feature'] : []);
            allow($fs->mtime())->toReturn(100);
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
            expect($captured)->toContain('Last-touched feature: features/adding.feature');
            expect($captured)->not()->toContain(getcwd());
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

        it('shows the model the working story files when a feature is red', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            $cwd = getcwd();
            $featuresDir = $cwd . '/features';
            $known = [
                $cwd . '/features/clearing.feature' => "Feature: Clearing\n  Scenario: Clears\n    When I clear completed tasks\n",
                $cwd . '/features/steps/clearing.steps.php' => "<?php\nwhen('I clear completed tasks', fn() => \$this->todoList->getTasks());\n",
                $cwd . '/spec/App/TodoList.spec.php' => "<?php\ndescribe('TodoList', function () { it('clears completed tasks', fn() => null); });\n",
                $cwd . '/src/App/TodoList.php' => "<?php\nclass TodoList { public function tasks(): array { return []; } }\n",
            ];
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath || $p === $featuresDir || isset($known[$p]) || in_array($p, [$cwd . '/spec', $cwd . '/src'], true));
            allow($fs->isDir())->toReturnUsing(fn(string $p): bool => in_array($p, [$featuresDir, $cwd . '/spec', $cwd . '/src'], true));
            allow($fs->scandir())->toReturnUsing(fn(string $d): array => match ($d) {
                $cwd . '/spec' => ['App/TodoList.spec.php'],
                $cwd . '/src' => ['App/TodoList.php'],
                default => [],
            });
            allow($fs->isFile())->toReturnUsing(fn(string $p): bool => isset($known[$p]));
            allow($fs->mtime())->toReturn(100);
            allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $yamlPath ? "ai:\n  provider: google\n  api_key: test-key\n" : ($known[$p] ?? ''));
            allow($fs->mkdir())->toReturn(null);
            allow($fs->write())->toReturn(null);

            $runner = new NextFakeRunner();
            $runner->outcome = new RunOutcome(null, new SuiteSummary(
                'red',
                ['examples' => 3, 'passes' => 3, 'failures' => 0, 'errors' => 0, 'pending' => 0],
                [],
                [],
                ['features' => 1, 'scenarios' => 1, 'steps' => 3, 'stepFailures' => 1, 'undefined' => 0],
                [['path' => 'features/clearing.feature', 'status' => 'red', 'undefined' => 0]],
            ));

            $replay = new ReplayProvider([
                new Response('', [new ToolCall('1', 'suggest_next', ['type' => 'implement', 'target' => 'App\\TodoList', 'reason' => 'The step calls getTasks() but the class only has tasks().'])]),
            ]);
            $cmd = new Next(new Configuration('.', $fs), $fs, null, $runner, $replay);

            $app = new Application();
            method_exists($app, 'addCommand') ? $app->addCommand($cmd) : $app->add($cmd);
            $tester = new CommandTester($app->find('next'));
            $tester->setInputs(['n']);
            $tester->execute([]);

            $context = $replay->requests[0]['messages'][1]->content;
            expect($context)->toContain('features/clearing.feature');
            expect($context)->toContain('I clear completed tasks');
            expect($context)->toContain('getTasks');                    // the steps file content
            expect($tester->getDisplay())->toContain('Implement');
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

            $app = new Application();
            method_exists($app, 'addCommand') ? $app->addCommand($cmd) : $app->add($cmd);
            $tester = new CommandTester($app->find('next'));
            $tester->setInputs(['n']);
            $tester->execute([]);

            expect($replay->requests)->toBe([]);                                  // deterministic: no model
            expect($tester->getDisplay())->toContain('undefined steps');
            expect($tester->getDisplay())->toContain('Write the steps.');
            expect($tester->getDisplay())->toContain('Would you like me to create that for you?');
        });

        it('actuates an accepted steps suggestion through generate', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath);
            allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $yamlPath ? "ai:\n  provider: google\n  api_key: test-key\n" : '');

            $configWithAi = new Configuration('.', $fs);
            $suggestFn = fn(array $aiConfig): array => [
                'type' => 'steps',
                'target' => 'features/adding.feature',
                'reason' => 'Undefined steps block the story.',
            ];
            $cmd = new Next($configWithAi, $fs, $suggestFn);

            $fakeGenerate = new class extends SymfonyCommand {
                public string $received = '';
                protected function configure(): void
                {
                    $this->setName('generate')->addArgument('instruction', InputArgument::IS_ARRAY);
                }
                protected function execute(InputInterface $input, OutputInterface $output): int
                {
                    $this->received = implode(' ', (array) $input->getArgument('instruction'));

                    return 0;
                }
            };

            $app = new Application();
            foreach ([$cmd, $fakeGenerate] as $command) {
                method_exists($app, 'addCommand') ? $app->addCommand($command) : $app->add($command);
            }

            $tester = new CommandTester($app->find('next'));
            $tester->setInputs(['Y']);
            $exitCode = $tester->execute([]);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('Write the steps for');
            expect($fakeGenerate->received)->toBe('the steps for features/adding.feature');
        });

        it('actuates an accepted implement suggestion for a class through generate', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath);
            allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $yamlPath ? "ai:\n  provider: google\n  api_key: test-key\n" : '');

            $configWithAi = new Configuration('.', $fs);
            $suggestFn = fn(array $aiConfig): array => [
                'type' => 'implement',
                'target' => 'App\\TodoList',
                'reason' => 'The suite is red: App\\TodoList, it deletes a task.',
            ];
            $cmd = new Next($configWithAi, $fs, $suggestFn);

            $fakeGenerate = new class extends SymfonyCommand {
                public string $received = '';
                protected function configure(): void
                {
                    $this->setName('generate')->addArgument('instruction', InputArgument::IS_ARRAY);
                }
                protected function execute(InputInterface $input, OutputInterface $output): int
                {
                    $this->received = implode(' ', (array) $input->getArgument('instruction'));

                    return 0;
                }
            };

            $app = new Application();
            foreach ([$cmd, $fakeGenerate] as $command) {
                method_exists($app, 'addCommand') ? $app->addCommand($command) : $app->add($command);
            }

            $tester = new CommandTester($app->find('next'));
            $tester->setInputs(['Y']);
            $exitCode = $tester->execute([]);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('Implement');
            expect($fakeGenerate->received)->toBe('implement App\\TodoList: The suite is red: App\\TodoList, it deletes a task.');
        });

        it('falls back to a run hint when the failing subject is no class', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath);
            allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $yamlPath ? "ai:\n  provider: google\n  api_key: test-key\n" : '');

            $configWithAi = new Configuration('.', $fs);
            $suggestFn = fn(array $aiConfig): array => [
                'type' => 'implement',
                'target' => 'deleting_a_task',
                'reason' => 'The suite is red: deleting_a_task. Make it green.',
            ];
            $cmd = new Next($configWithAi, $fs, $suggestFn);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute([]);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('run');
            expect($tester->getDisplay())->not()->toContain('Would you like me to create that for you?');
        });

        it('emits the agent document with the actuating command under --format=agent, never a prompt', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath);
            allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $yamlPath ? "ai:\n  provider: google\n  api_key: test-key\n" : '');

            $configWithAi = new Configuration('.', $fs);
            $suggestFn = fn(array $aiConfig): array => [
                'type' => 'feature',
                'target' => 'deleting_a_task',
                'reason' => 'The list only grows.',
            ];
            $cmd = new Next($configWithAi, $fs, $suggestFn);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute(['--format' => 'agent']);

            expect($exitCode)->toBe(0);
            $document = json_decode(trim($tester->getDisplay()), true);
            expect($document['command']['command'])->toBe('next');
            expect($document['suggestion']['type'])->toBe('feature');
            expect($document['suggestion']['run'])->toContain('generate "a feature for deleting_a_task"');
            expect($tester->getDisplay())->not()->toContain('Would you like');
            expect($tester->getDisplay())->not()->toContain('Analysing');
        });

        it('keeps the growth steer inside the agent document', function (Filesystem $fs) {
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
            $exitCode = $tester->execute(['--format' => 'agent']);

            expect($exitCode)->toBe(0);
            $document = json_decode(trim($tester->getDisplay()), true);
            expect($document['suggestion']['type'])->toBe('info');
            expect($document['prose'] ?? $document['suggestion']['reason'])->toContain('already refactored');
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
