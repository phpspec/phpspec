<?php

use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\Response;
use PhpSpec\Ai\ToolCall;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Refactor;
use PhpSpec\Console\Command\Refactor\RefactorResult;
use PhpSpec\Filesystem;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

// Builds a Refactor command wired to a scripted provider that proposes one
// Extract Method refactoring, over a mocked project, capturing writes; the
// consent examples drive the REAL agent flow, not the refactorFn shortcut.
function refactorConsentWorld(Filesystem $fs, array &$written): Refactor
{
    $yamlPath = './phpspec.yaml';
    $cwd = getcwd();
    $srcPath = $cwd . '/src/App/Good.php';
    $specPath = $cwd . '/spec/App/Good.spec.php';

    allow($fs->exists())->toReturnUsing(fn(string $path): bool => in_array($path, [$yamlPath, $srcPath, $specPath], true));
    allow($fs->read())->toReturnUsing(function (string $path) use ($yamlPath): string {
        if ($path === $yamlPath) {
            return "ai:\n  provider: google\n  api_key: test-key\n";
        }

        return "<?php\nclass Good {}\n";
    });
    allow($fs->write())->toReturnUsing(function (string $path, string $content) use (&$written) {
        $written[$path] = $content;
    });

    $provider = new class implements ProviderInterface {
        public int $turn = 0;

        public function chat(array $messages, array $options = []): Response
        {
            if (++$this->turn === 1) {
                return new Response('', [new ToolCall('t1', 'apply_refactoring', [
                    'content' => "<?php\nclass Good { /* extracted */ }\n",
                    'technique' => 'Extract Method',
                    'description' => 'Pull the guard out',
                ])]);
            }

            return new Response('done');
        }
    };

    $specRunner = fn(string $path): array => [0, '1 pass'];

    $cmd = new Refactor(new Configuration('.', $fs), $fs, $specRunner, null, $provider);

    // Registered on an application so the command has a helper set: the
    // consent question needs the question helper, exactly as in production.
    $app = new Application('phpspec-test', '1.0');
    $app->setAutoExit(false);
    $app->{method_exists($app, 'addCommand') ? 'addCommand' : 'add'}($cmd);

    return $cmd;
}

describe(Refactor::class, function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
    });

    context('consent before applying', function () {

        it('shows the proposal and asks before applying; declining leaves the file untouched', function (Filesystem $fs) {
            $written = [];
            $cmd = refactorConsentWorld($fs, $written);

            $tester = new CommandTester($cmd);
            $tester->setInputs(['n']);
            $exitCode = $tester->execute(['target' => 'App\\Good']);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('Extract Method');
            expect($tester->getDisplay())->toContain('Apply this refactoring?');
            expect($tester->getDisplay())->toContain('Not applied');
            expect(count($written))->toBe(0);
        });

        it('applies on yes and verifies', function (Filesystem $fs) {
            $written = [];
            $cmd = refactorConsentWorld($fs, $written);

            $tester = new CommandTester($cmd);
            $tester->setInputs(['']);
            $exitCode = $tester->execute(['target' => 'App\\Good']);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('Specs still pass');
            expect(implode('', $written))->toContain('extracted');
        });

        it('applies without asking when non-interactive', function (Filesystem $fs) {
            $written = [];
            $cmd = refactorConsentWorld($fs, $written);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute(['target' => 'App\\Good'], ['interactive' => false]);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->not()->toContain('Apply this refactoring?');
            expect(implode('', $written))->toContain('extracted');
        });

    });

    it('instantiates', function (Filesystem $fs) {
        $config = new Configuration('.', $fs);
        $cmd = new Refactor($config, $fs);
        expect($cmd)->toBeAnInstanceOf(Refactor::class);
    });

    context('target resolution', function () {

        it('resolves a FQCN to src and spec paths', function (Filesystem $fs) {
            $config = new Configuration('.', $fs);
            $cmd = new Refactor($config, $fs);

            $result = $cmd->resolveTarget('App\\Calculator');

            expect($result)->not()->toBeNull();
            expect($result[0])->toEndWith('src/App/Calculator.php');
            expect($result[1])->toEndWith('spec/App/Calculator.spec.php');
            expect($result[2])->toBeNull();
        });

        it('resolves a FQCN::method with method focus', function (Filesystem $fs) {
            $config = new Configuration('.', $fs);
            $cmd = new Refactor($config, $fs);

            $result = $cmd->resolveTarget('App\\Calculator::sum');

            expect($result)->not()->toBeNull();
            expect($result[0])->toEndWith('src/App/Calculator.php');
            expect($result[1])->toEndWith('spec/App/Calculator.spec.php');
            expect($result[2])->toBe('sum');
        });

        it('resolves a spec file path and infers src', function (Filesystem $fs) {
            $config = new Configuration('.', $fs);
            $cmd = new Refactor($config, $fs);

            $result = $cmd->resolveTarget('spec/App/Calculator.spec.php');

            expect($result)->not()->toBeNull();
            expect($result[0])->toEndWith('src/App/Calculator.php');
            expect($result[1])->toEndWith('spec/App/Calculator.spec.php');
            expect($result[2])->toBeNull();
        });

        it('resolves a deeply nested FQCN', function (Filesystem $fs) {
            $config = new Configuration('.', $fs);
            $cmd = new Refactor($config, $fs);

            $result = $cmd->resolveTarget('App\\Domain\\Service\\Calculator');

            expect($result[0])->toEndWith('src/App/Domain/Service/Calculator.php');
            expect($result[1])->toEndWith('spec/App/Domain/Service/Calculator.spec.php');
            expect($result[2])->toBeNull();
        });

        it('resolves a spec file with .spec.php in the name', function (Filesystem $fs) {
            $config = new Configuration('.', $fs);
            $cmd = new Refactor($config, $fs);

            $result = $cmd->resolveTarget('spec/App/Service.spec.php');

            expect($result[0])->toEndWith('src/App/Service.php');
            expect($result[1])->toEndWith('spec/App/Service.spec.php');
            expect($result[2])->toBeNull();
        });
    });

    context('command execution', function () {

        it('returns error when AI config is missing', function (Filesystem $fs) {
            $config = new Configuration('.', $fs);
            $cmd = new Refactor($config, $fs);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute(['target' => 'App\\Calculator']);

            expect($exitCode)->toBe(1);
            expect($tester->getDisplay())->toContain('AI configuration required');
        });

        it('returns error when source file does not exist', function (Filesystem $fs) {
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
            $cmd = new Refactor($configWithAi, $fs);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute(['target' => 'App\\Missing']);

            expect($exitCode)->toBe(1);
            expect($tester->getDisplay())->toContain('Source file not found');
        });

        it('returns error when spec file does not exist', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            $cwd = getcwd();
            $srcPath = $cwd . '/src/App/Present.php';

            $fs->______PhpSpecStubReturnUsing('exists', function (string $path) use ($yamlPath, $srcPath): bool {
                return $path === $yamlPath || $path === $srcPath;
            });
            $fs->______PhpSpecStubReturnUsing('read', function (string $path) use ($yamlPath): string {
                if ($path === $yamlPath) {
                    return "ai:\n  provider: google\n  api_key: test-key\n";
                }
                return '';
            });

            $configWithAi = new Configuration('.', $fs);
            $cmd = new Refactor($configWithAi, $fs);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute(['target' => 'App\\Present']);

            expect($exitCode)->toBe(1);
            expect($tester->getDisplay())->toContain('Spec file not found');
        });

        it('returns error when baseline specs fail', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            $cwd = getcwd();
            $srcPath = $cwd . '/src/App/Broken.php';
            $specPath = $cwd . '/spec/App/Broken.spec.php';

            $fs->______PhpSpecStubReturnUsing('exists', function (string $path) use ($yamlPath, $srcPath, $specPath): bool {
                return $path === $yamlPath || $path === $srcPath || $path === $specPath;
            });
            $fs->______PhpSpecStubReturnUsing('read', function (string $path) use ($yamlPath): string {
                if ($path === $yamlPath) {
                    return "ai:\n  provider: google\n  api_key: test-key\n";
                }
                return '';
            });

            $configWithAi = new Configuration('.', $fs);
            $specRunner = fn(string $path): array => [1, '1 example (1 failure)'];
            $cmd = new Refactor($configWithAi, $fs, $specRunner);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute(['target' => 'App\\Broken']);

            expect($exitCode)->toBe(1);
            expect($tester->getDisplay())->toContain('Specs must pass before refactoring');
        });

        it('displays successful refactoring result', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            $cwd = getcwd();
            $srcPath = $cwd . '/src/App/Good.php';
            $specPath = $cwd . '/spec/App/Good.spec.php';

            $fs->______PhpSpecStubReturnUsing('exists', function (string $path) use ($yamlPath, $srcPath, $specPath): bool {
                return $path === $yamlPath || $path === $srcPath || $path === $specPath;
            });
            $fs->______PhpSpecStubReturnUsing('read', function (string $path) use ($yamlPath): string {
                if ($path === $yamlPath) {
                    return "ai:\n  provider: google\n  api_key: test-key\n";
                }
                return '';
            });

            $configWithAi = new Configuration('.', $fs);
            $specRunner = fn(string $path): array => [0, '1 pass'];
            $refactorFn = fn(string $src, string $spec, ?string $method) => new RefactorResult(
                success: true,
                technique: 'Extract Method',
                description: 'Extracted helper method',
                diff: '+ private function helper()',
                specOutput: '1 pass',
            );
            $cmd = new Refactor($configWithAi, $fs, $specRunner, $refactorFn);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute(['target' => 'App\\Good']);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('Refactoring technique:');
            expect($tester->getDisplay())->toContain('Extract Method');
            expect($tester->getDisplay())->toContain('Specs still pass');
        });

        it('displays failed refactoring result', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            $cwd = getcwd();
            $srcPath = $cwd . '/src/App/Risky.php';
            $specPath = $cwd . '/spec/App/Risky.spec.php';

            $fs->______PhpSpecStubReturnUsing('exists', function (string $path) use ($yamlPath, $srcPath, $specPath): bool {
                return $path === $yamlPath || $path === $srcPath || $path === $specPath;
            });
            $fs->______PhpSpecStubReturnUsing('read', function (string $path) use ($yamlPath): string {
                if ($path === $yamlPath) {
                    return "ai:\n  provider: google\n  api_key: test-key\n";
                }
                return '';
            });

            $configWithAi = new Configuration('.', $fs);
            $specRunner = fn(string $path): array => [0, '1 pass'];
            $refactorFn = fn(string $src, string $spec, ?string $method) => new RefactorResult(
                success: false,
                technique: 'Inline Variable',
                description: 'Attempted inline but specs broke',
                diff: '- $temp = $a;',
                specOutput: '1 failure',
            );
            $cmd = new Refactor($configWithAi, $fs, $specRunner, $refactorFn);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute(['target' => 'App\\Risky']);

            expect($exitCode)->toBe(1);
            expect($tester->getDisplay())->toContain('Refactoring technique:');
            expect($tester->getDisplay())->toContain('Inline Variable');
            expect($tester->getDisplay())->toContain('Refactoring reverted');
        });

        it('displays successful result with empty diff', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            $cwd = getcwd();
            $srcPath = $cwd . '/src/App/NoDiff.php';
            $specPath = $cwd . '/spec/App/NoDiff.spec.php';

            $fs->______PhpSpecStubReturnUsing('exists', function (string $path) use ($yamlPath, $srcPath, $specPath): bool {
                return $path === $yamlPath || $path === $srcPath || $path === $specPath;
            });
            $fs->______PhpSpecStubReturnUsing('read', function (string $path) use ($yamlPath): string {
                if ($path === $yamlPath) {
                    return "ai:\n  provider: google\n  api_key: test-key\n";
                }
                return '';
            });

            $configWithAi = new Configuration('.', $fs);
            $specRunner = fn(string $path): array => [0, '1 pass'];
            $refactorFn = fn(string $src, string $spec, ?string $method) => new RefactorResult(
                success: true,
                technique: 'Rename Variable',
                description: 'Renamed for clarity',
                diff: '',
                specOutput: '1 pass',
            );
            $cmd = new Refactor($configWithAi, $fs, $specRunner, $refactorFn);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute(['target' => 'App\\NoDiff']);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('Rename Variable');
            expect($tester->getDisplay())->toContain('Specs still pass');
        });

        it('loads bootstrap when configured', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            $cwd = getcwd();
            $srcPath = $cwd . '/src/App/Booted.php';
            $specPath = $cwd . '/spec/App/Booted.spec.php';

            $fs->______PhpSpecStubReturnUsing('exists', function (string $path) use ($yamlPath, $srcPath, $specPath): bool {
                return $path === $yamlPath || $path === $srcPath || $path === $specPath;
            });
            $fs->______PhpSpecStubReturnUsing('read', function (string $path) use ($yamlPath): string {
                if ($path === $yamlPath) {
                    return "ai:\n  provider: google\n  api_key: test-key\nbootstrap: vendor/autoload.php\n";
                }
                return '';
            });

            $configWithAi = new Configuration('.', $fs);
            $specRunner = fn(string $path): array => [0, '1 pass'];
            $refactorFn = fn(string $src, string $spec, ?string $method) => new RefactorResult(
                success: true,
                technique: 'Extract Method',
                description: 'Extracted method',
                diff: '+ new line',
                specOutput: '1 pass',
            );
            $cmd = new Refactor($configWithAi, $fs, $specRunner, $refactorFn);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute(['target' => 'App\\Booted']);

            // vendor/autoload.php exists, so bootstrap loads fine
            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('Specs still pass');
        });

        it('displays no-refactoring-needed result', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            $cwd = getcwd();
            $srcPath = $cwd . '/src/App/Clean.php';
            $specPath = $cwd . '/spec/App/Clean.spec.php';

            $fs->______PhpSpecStubReturnUsing('exists', function (string $path) use ($yamlPath, $srcPath, $specPath): bool {
                return $path === $yamlPath || $path === $srcPath || $path === $specPath;
            });
            $fs->______PhpSpecStubReturnUsing('read', function (string $path) use ($yamlPath): string {
                if ($path === $yamlPath) {
                    return "ai:\n  provider: google\n  api_key: test-key\n";
                }
                return '';
            });

            $configWithAi = new Configuration('.', $fs);
            $specRunner = fn(string $path): array => [0, '1 pass'];
            $refactorFn = fn(string $src, string $spec, ?string $method) => new RefactorResult(
                success: false,
                technique: 'None',
                description: 'Code is already clean.',
                diff: '',
                specOutput: '',
            );
            $cmd = new Refactor($configWithAi, $fs, $specRunner, $refactorFn);

            $tester = new CommandTester($cmd);
            $exitCode = $tester->execute(['target' => 'App\\Clean']);

            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('Code is already clean');
        });
    });
});
