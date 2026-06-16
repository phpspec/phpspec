<?php

use PhpSpec\Configuration;
use PhpSpec\Console\Command\Next;
use PhpSpec\Filesystem;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

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
            $fs->______PhpSpecStubReturnUsing('exists', function (string $path) use ($yamlPath): bool {
                return $path === $yamlPath;
            });
            $fs->______PhpSpecStubReturnUsing('read', function (string $path) use ($yamlPath): string {
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

        it('displays a feature suggestion with confirmation', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            $fs->______PhpSpecStubReturnUsing('exists', function (string $path) use ($yamlPath): bool {
                return $path === $yamlPath;
            });
            $fs->______PhpSpecStubReturnUsing('read', function (string $path) use ($yamlPath): string {
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

        it('displays an example suggestion with exemplify hint', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            $fs->______PhpSpecStubReturnUsing('exists', function (string $path) use ($yamlPath): bool {
                return $path === $yamlPath;
            });
            $fs->______PhpSpecStubReturnUsing('read', function (string $path) use ($yamlPath): string {
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
            $fs->______PhpSpecStubReturnUsing('exists', function (string $path) use ($yamlPath): bool {
                return $path === $yamlPath;
            });
            $fs->______PhpSpecStubReturnUsing('read', function (string $path) use ($yamlPath): string {
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
            $fs->______PhpSpecStubReturnUsing('exists', function (string $path) use ($yamlPath): bool {
                return $path === $yamlPath;
            });
            $fs->______PhpSpecStubReturnUsing('read', function (string $path) use ($yamlPath): string {
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

        it('passes AI config to the suggest function', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            $fs->______PhpSpecStubReturnUsing('exists', function (string $path) use ($yamlPath): bool {
                return $path === $yamlPath;
            });
            $fs->______PhpSpecStubReturnUsing('read', function (string $path) use ($yamlPath): string {
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
