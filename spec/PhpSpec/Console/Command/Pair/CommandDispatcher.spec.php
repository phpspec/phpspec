<?php

use PhpSpec\Ai\Agent\Agent;
use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\Response;
use PhpSpec\Ai\ToolCall;
use PhpSpec\CodeGeneration\ClassGenerator;
use PhpSpec\CodeGeneration\SpecGenerator;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Pair\AiAssistant;
use PhpSpec\Console\Command\Pair\Chooser;
use PhpSpec\Console\Command\Pair\CommandDispatcher;
use PhpSpec\Console\Command\Pair\PairOutput;
use PhpSpec\Console\Command\Pair\RoleState;
use PhpSpec\Console\Command\Pair\SpecRunner;
use PhpSpec\Console\Command\Run\RunOutcome;
use PhpSpec\Console\Command\Run\SuiteSummary;
use PhpSpec\Filesystem;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

require_once __DIR__ . '/../../../Ai/ReplayProvider.php';

// A fake SpecRunner so the REPL can be tested without spawning a real run
// subprocess; it records the arguments it was asked to run.
class CommandDispatcherFakeRunner implements SpecRunner
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

// Builds a dispatcher whose Configuration reports an ai: block for the given
// provider, by faking the phpspec.yaml the Configuration reads.
function commandDispatcherWithAi(Filesystem $fs, PairOutput $pairOutput, SpecRunner $specRunner, string $provider): CommandDispatcher
{
    $yamlPath = './phpspec.yaml';
    allow($fs->exists())->toReturnUsing(fn(string $path): bool => $path === $yamlPath);
    allow($fs->read())->toReturnUsing(fn(string $path): string => $path === $yamlPath ? "ai:\n  provider: $provider\n  api_key: test-key\n" : '');

    return new CommandDispatcher(
        new SpecGenerator('spec', $fs),
        new ClassGenerator('src', $fs),
        new Configuration('.', $fs),
        $pairOutput,
        false,
        $fs,
        specRunner: $specRunner,
    );
}

describe(CommandDispatcher::class, function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
        allow($fs->scandir())->toReturn([]);
    });

    let('buffer', fn() => new BufferedOutput());
    let('pairOutput', fn() => new PairOutput($this->buffer));
    let('config', fn(Filesystem $fs) => new Configuration('.', $fs));
    let('specRunner', fn() => new CommandDispatcherFakeRunner());
    let('dispatcher', fn(Filesystem $fs) => new CommandDispatcher(
        new SpecGenerator('spec', $fs),
        new ClassGenerator('src', $fs),
        $this->config,
        $this->pairOutput,
        false,
        $fs,
        specRunner: $this->specRunner,
    ));

    it('instantiates', fn() => expect($this->dispatcher)->toBeAnInstanceOf(CommandDispatcher::class));

    it('normalises slash paths to namespaced classes in /describe', function (Filesystem $fs) {
        $written = [];
        allow($fs->write())->toReturnUsing(function (string $path, string $content) use (&$written) {
            $written[$path] = $content;
        });

        $this->dispatcher->dispatch('/describe App/Basket');

        $classContents = implode("\n", array_filter(
            $written,
            fn(string $path) => str_ends_with(str_replace('\\', '/', $path), 'src/App/Basket.php'),
            ARRAY_FILTER_USE_KEY,
        ));
        expect($classContents)->toContain('namespace App;');
        expect($classContents)->toContain('class Basket');
        expect($classContents)->not()->toContain('class App/Basket');
    });

    it('returns CONTINUE for /help', function () {
        expect($this->dispatcher->dispatch('/help'))->toBe(CommandDispatcher::CONTINUE);
    });

    it('shows help text for /help', function () {
        $this->dispatcher->dispatch('/help');
        $output = $this->buffer->fetch();
        expect($output)->toContain('Available commands');
    });

    it('returns QUIT for /quit', function () {
        expect($this->dispatcher->dispatch('/quit'))->toBe(CommandDispatcher::QUIT);
    });

    it('returns QUIT for /exit', function () {
        expect($this->dispatcher->dispatch('/exit'))->toBe(CommandDispatcher::QUIT);
    });

    it('returns CONTINUE for empty input', function () {
        expect($this->dispatcher->dispatch(''))->toBe(CommandDispatcher::CONTINUE);
    });

    it('returns CONTINUE for an unknown slash command', function () {
        expect($this->dispatcher->dispatch('/foobar'))->toBe(CommandDispatcher::CONTINUE);
    });

    it('shows an error for an unknown slash command', function () {
        $this->dispatcher->dispatch('/foobar');
        $output = $this->buffer->fetch();
        expect($output)->toContain('Unknown command: /foobar');
    });

    it('returns CONTINUE for /clear', function () {
        expect($this->dispatcher->dispatch('/clear'))->toBe(CommandDispatcher::CONTINUE);
    });

    it('shows error when /describe is missing argument', function () {
        $this->dispatcher->dispatch('/describe');
        $output = $this->buffer->fetch();
        expect($output)->toContain('Usage: /describe');
    });

    it('describe auto-generates spec and shows confirmation', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $p) => match (true) {
            str_ends_with($p, '.spec.php') => false,
            default => false,
        });
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);

        $result = $this->dispatcher->dispatch('/describe Acme\Greeter');
        expect($result)->toBe(CommandDispatcher::CONTINUE);
        $output = $this->buffer->fetch();
        expect($output)->toContain('Specification for');
        expect($output)->toContain('Acme\Greeter');
        expect($output)->toContain('.spec.php');
    });

    it('describe offers class creation after spec generation', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);

        $this->dispatcher->dispatch('/describe Acme\Greeter');
        $output = $this->buffer->fetch();
        expect($output)->toContain('Do you want me to create class');
    });

    it('describe shows generated class file', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $p) => match (true) {
            str_ends_with($p, '.php') && !str_ends_with($p, '.spec.php') => false,
            default => false,
        });
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);

        $this->dispatcher->dispatch('/describe Acme\Greeter');
        $output = $this->buffer->fetch();
        expect($output)->toContain('Do you want me to create class');
    });

    it('describe offers to run specs after generation', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);

        $this->dispatcher->dispatch('/describe Acme\Greeter');
        $output = $this->buffer->fetch();
        expect($output)->toContain('Do you want to run specs now');
    });

    it('describe shows existing spec message when spec exists', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $p) => str_ends_with($p, '.spec.php'));

        $this->dispatcher->dispatch('/describe Acme\Greeter');
        $output = $this->buffer->fetch();
        expect($output)->toContain('Spec already exists');
    });

    it('describe displays spec file content after generation', function (Filesystem $fs) {
        $specCalls = 0;
        allow($fs->exists())->toReturnUsing(function (string $p) use (&$specCalls) {
            if (str_ends_with($p, '.spec.php')) {
                // First call from SpecGenerator: not exists. Second call from CommandDispatcher: exists.
                return $specCalls++ > 0;
            }
            return false;
        });
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);
        allow($fs->read())->toReturn("<?php\n// spec content");

        $this->dispatcher->dispatch('/describe Acme\Greeter');
        $output = $this->buffer->fetch();
        expect($output)->toContain('[NEW FILE]');
        expect($output)->toContain('spec content');
    });

    it('describe displays class file content after generation', function (Filesystem $fs) {
        $specCalls = 0;
        $classCalls = 0;
        allow($fs->exists())->toReturnUsing(function (string $p) use (&$specCalls, &$classCalls) {
            if (str_ends_with($p, '.spec.php')) {
                return $specCalls++ > 0;
            }
            if (str_ends_with($p, '.php')) {
                // First call from ClassGenerator: not exists. Second from CommandDispatcher: exists.
                return $classCalls++ > 0;
            }
            return false;
        });
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);
        allow($fs->read())->toReturn("<?php\nclass Greeter {}");

        $this->dispatcher->dispatch('/describe Acme\Greeter');
        $output = $this->buffer->fetch();
        expect($output)->toContain('class Greeter');
    });

    it('shows error when /exemplify is missing arguments', function () {
        $this->dispatcher->dispatch('/exemplify');
        $output = $this->buffer->fetch();
        expect($output)->toContain('Usage: /exemplify');
    });

    it('shows error when /exemplify is missing method argument', function () {
        $this->dispatcher->dispatch('/exemplify Acme\Calculator');
        $output = $this->buffer->fetch();
        expect($output)->toContain('Usage: /exemplify');
    });

    it('exemplify adds example and shows confirmation', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $p) => match (true) {
            str_ends_with($p, '.spec.php') => false,
            default => false,
        });
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);
        allow($fs->read())->toReturn("<?php\ndescribe(Calculator::class, function() {\n});");

        $result = $this->dispatcher->dispatch('/exemplify Acme\Calculator add');
        expect($result)->toBe(CommandDispatcher::CONTINUE);
        $output = $this->buffer->fetch();
        expect($output)->toContain('Example for');
        expect($output)->toContain('Acme\Calculator::add');
    });

    it('exemplify generates spec when it does not exist', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);
        allow($fs->read())->toReturn("<?php\ndescribe(Calculator::class, function() {\n});");

        $this->dispatcher->dispatch('/exemplify Acme\Calculator add');
        $output = $this->buffer->fetch();
        expect($output)->toContain('Specification for');
    });

    it('exemplify reports when an example already exists instead of duplicating it', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\ndescribe(Calculator::class, function() {\n    it(\"should add\", fn() => expect(\$this->calculator->add())->toBe(null));\n});");

        $this->dispatcher->dispatch('/exemplify Acme\Calculator add');
        $output = $this->buffer->fetch();
        expect($output)->toContain('already exists');
        expect($fs->write('', ''))->not()->toBeCalled();
    });

    it('runs specs with /run', function () {
        $result = $this->dispatcher->dispatch('/run');
        expect($result)->toBe(CommandDispatcher::CONTINUE);
        expect($this->specRunner->arguments)->toBe(['']);
    });

    it('runs specs at a specific path with /run', function () {
        $result = $this->dispatcher->dispatch('/run spec/NonExistent');
        expect($result)->toBe(CommandDispatcher::CONTINUE);
        expect($this->specRunner->arguments)->toBe(['spec/NonExistent']);
    });

    context('commands read as commands with or without the slash; prose stays with the AI', function () {
        it('runs a slash command with a path argument', function () {
            $result = $this->dispatcher->dispatch('/run spec/App');
            expect($result)->toBe(CommandDispatcher::CONTINUE);
            expect($this->specRunner->arguments)->toBe(['spec/App']);
        });

        it('routes non-slash prose to the AI (explains AI is off without a provider)', function () {
            $this->dispatcher->dispatch('run the scenarios and fix them');
            $output = $this->buffer->fetch();
            // Prose after the command word → an AI prompt; with no AI it explains, without calling it an unknown command.
            expect($output)->toContain('natural language');
            expect($output)->not()->toContain('Unknown command');
            expect($this->specRunner->arguments)->toBe([]);
        });

        it('routes a bare "describe Class" to the describe command', function (Filesystem $fs) {
            $written = [];
            allow($fs->write())->toReturnUsing(function (string $path, string $content) use (&$written) {
                $written[$path] = $content;
            });

            $this->dispatcher->dispatch('describe Acme\Foo');

            expect($this->buffer->fetch())->toContain('Specification for');
            $specFiles = array_filter(
                $written,
                fn(string $path) => str_ends_with(str_replace('\\', '/', $path), 'spec/Acme/Foo.spec.php'),
                ARRAY_FILTER_USE_KEY,
            );
            expect($specFiles)->not()->toBe([]);
        });

        it('routes a bare "next" like /next', function () {
            $this->dispatcher->dispatch('next');
            expect($this->specRunner->arguments)->toContain('--all');
        });

        it('translates "run features" into a story-only run', function () {
            $this->dispatcher->dispatch('run features');
            expect($this->specRunner->arguments)->toBe(['--story']);
        });

        it('translates "run all" into a full-suite run', function () {
            $this->dispatcher->dispatch('run all');
            expect($this->specRunner->arguments)->toBe(['--all']);
        });

        it('translates "run specs" into the default spec run', function () {
            $this->dispatcher->dispatch('run specs');
            expect($this->specRunner->arguments)->toBe(['']);
        });

        it('passes "run --all" through without a slash', function () {
            $this->dispatcher->dispatch('run --all');
            expect($this->specRunner->arguments)->toBe(['--all']);
        });

        it('translates "/run features" the same as the plain form', function () {
            $this->dispatcher->dispatch('/run features');
            expect($this->specRunner->arguments)->toBe(['--story']);
        });

        it('passes options through /run to the runner', function () {
            $this->dispatcher->dispatch('/run --stop-on-failure');
            expect($this->specRunner->arguments)->toBe(['--stop-on-failure']);
        });

        it('keeps a path and its option together for the runner', function () {
            $this->dispatcher->dispatch('/run spec/App --stop-on-failure');
            expect($this->specRunner->arguments)->toBe(['spec/App --stop-on-failure']);
        });

        it('never rewrites an option value that matches a suite keyword', function () {
            $this->dispatcher->dispatch('/run --filter features');
            expect($this->specRunner->arguments)->toBe(['--filter features']);
        });

        it('routes unrecognized prose to the AI fallback', function () {
            $this->dispatcher->dispatch('hello world');
            $output = $this->buffer->fetch();
            expect($output)->toContain('natural language');
            expect($output)->not()->toContain('Unknown command');
        });
    });

    context('a /generate chooser note becomes the follow-up round', function () {

        function generateNoteWorld(Filesystem $fs, PairOutput $pairOutput, object $example): CommandDispatcher
        {
            $yamlPath = './phpspec.yaml';
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath);
            allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $yamlPath ? "ai:\n  provider: google\n  api_key: test-key\n" : '');
            allow($fs->write())->toReturnUsing(function (string $p, string $c) use ($example) {
                $example->written[$p] = $c;
            });
            allow($fs->mkdir())->toReturn(null);

            $config = new Configuration('.', $fs);

            return new CommandDispatcher(
                new SpecGenerator('spec', $fs),
                new ClassGenerator('src', $fs),
                $config,
                $pairOutput,
                true,
                $fs,
                chooser: new Chooser($pairOutput, true, function () use ($example) {
                    return array_shift($example->answers) ?? '';
                }),
                specRunner: $example->specRunner,
                agent: new Agent($config, $fs, $example->provider),
            );
        }

        beforeEach(function () {
            $this->written = [];
            $this->answers = [];
            $this->provider = new class implements ProviderInterface {
                /** @var list<array> */
                public array $rounds = [];

                public function chat(array $messages, array $options = []): Response
                {
                    $this->rounds[] = $messages;
                    $content = count($this->rounds) === 1
                        ? "<?php\nclass Calc { /* v1 */ }"
                        : "<?php\nclass Calc { public function add() {} /* v2 */ }";

                    return new Response('', [new ToolCall('t', 'propose_edit', ['path' => 'src/App/Calc.php', 'content' => $content])]);
                }
            };
        });

        it('feeds a declined-with-note proposal back as the follow-up instruction', function (Filesystem $fs) {
            $dispatcher = generateNoteWorld($fs, $this->pairOutput, $this);

            $this->answers = ['3, implement add for real', '1'];
            $dispatcher->dispatch('/generate implement add on App\\Calc');

            expect(count($this->provider->rounds))->toBe(2);
            expect((string) json_encode($this->provider->rounds[1]))->toContain('implement add for real');
            expect((string) json_encode($this->provider->rounds[1]))->toContain('declined');
            $calc = array_filter($this->written, fn(string $p) => str_ends_with(str_replace('\\', '/', $p), 'src/App/Calc.php'), ARRAY_FILTER_USE_KEY);
            expect(implode('', $calc))->toContain('v2');
        });

        it('runs an accepted-with-note follow-up after applying the proposal', function (Filesystem $fs) {
            $dispatcher = generateNoteWorld($fs, $this->pairOutput, $this);

            $this->answers = ['1, now cover subtraction too', '1'];
            $dispatcher->dispatch('/generate implement add on App\\Calc');

            expect(count($this->provider->rounds))->toBe(2);
            expect((string) json_encode($this->provider->rounds[1]))->toContain('now cover subtraction too');
            expect((string) json_encode($this->provider->rounds[1]))->toContain('applied');
            $calc = array_filter($this->written, fn(string $p) => str_ends_with(str_replace('\\', '/', $p), 'src/App/Calc.php'), ARRAY_FILTER_USE_KEY);
            expect(implode('', $calc))->toContain('v2');
        });

        it('stays a single round when the answer carries no note', function (Filesystem $fs) {
            $dispatcher = generateNoteWorld($fs, $this->pairOutput, $this);

            $this->answers = ['1'];
            $dispatcher->dispatch('/generate implement add on App\\Calc');

            expect(count($this->provider->rounds))->toBe(1);
        });

    });

    context('next favours features, outside-in', function () {
        it('runs the features too (--all) when suggesting the next step', function () {
            $this->dispatcher->dispatch('/next');

            expect($this->specRunner->arguments)->toContain('--all');
        });

        it('prints feature-first advice when features are present and no AI is configured', function () {
            $this->specRunner->outcome = new RunOutcome(null, new SuiteSummary(
                'green',
                ['examples' => 0, 'passes' => 0, 'failures' => 0, 'errors' => 0, 'pending' => 0],
                [],
                [],
                ['features' => 1, 'scenarios' => 1, 'steps' => 3, 'stepFailures' => 0, 'undefined' => 3],
                [['path' => 'features/adding.feature', 'status' => 'todo', 'undefined' => 3]],
            ));

            $this->dispatcher->dispatch('/next');

            expect($this->buffer->fetch())->toContain('adding.feature');
        });

        it('hands the AI the outside-in next.txt coaching, in both roles', function (Filesystem $fs) {
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => str_ends_with($p, '/Prompts/next.txt'));
            allow($fs->read())->toReturnUsing(fn(string $p): string => str_ends_with($p, '/Prompts/next.txt') ? 'OUTSIDE-IN feature-first BABY STEP coaching' : '');

            foreach ([new RoleState(\PhpSpec\Console\Command\Pair\PairRole::HumanDrives), new RoleState(\PhpSpec\Console\Command\Pair\PairRole::AiDrives)] as $roleState) {
                $captured = null;
                $provider = new class implements ProviderInterface {
                    /** @var callable|null */
                    public $responder = null;

                    public function chat(array $messages, array $options = []): Response
                    {
                        return ($this->responder)($messages, $options);
                    }
                };
                $provider->responder = function (array $messages) use (&$captured): Response {
                    $captured = $messages;

                    return new Response('Suggested the next scenario.');
                };

                $ai = new AiAssistant($provider, $this->config, $this->pairOutput, 'test-model', $fs, false, null, new Chooser($this->pairOutput, false), $roleState, $this->specRunner);
                $dispatcher = new CommandDispatcher(
                    new SpecGenerator('spec', $fs),
                    new ClassGenerator('src', $fs),
                    $this->config,
                    $this->pairOutput,
                    false,
                    $fs,
                    specRunner: $this->specRunner,
                    roleState: $roleState,
                    ai: $ai,
                );

                $dispatcher->dispatch('/next');

                expect((string) json_encode($captured))->toContain('OUTSIDE-IN');
            }
        });
    });

    context('AI provider readiness', function () {
        it('is ready, with no failure reason, when a working provider is configured', function (Filesystem $fs) {
            $dispatcher = commandDispatcherWithAi($fs, $this->pairOutput, $this->specRunner, 'google');

            expect($dispatcher->aiIsReady())->toBe(true);
            expect($dispatcher->aiUnavailableReason())->toBeNull();
        });

        it('is not ready, with a reason, when the provider package is not installed', function (Filesystem $fs) {
            $dispatcher = commandDispatcherWithAi($fs, $this->pairOutput, $this->specRunner, 'anthropic');

            expect($dispatcher->aiIsReady())->toBe(false);
            expect($dispatcher->aiUnavailableReason())->toContain('anthropic');
        });

        it('degrades gracefully instead of crashing when the provider name is unknown', function (Filesystem $fs) {
            $dispatcher = commandDispatcherWithAi($fs, $this->pairOutput, $this->specRunner, 'nonesuch');

            expect($dispatcher->aiIsReady())->toBe(false);
            expect($dispatcher->aiUnavailableReason())->toContain('nonesuch');
        });

        it('explains why a prompt could not reach the AI when the provider cannot start', function (Filesystem $fs) {
            $dispatcher = commandDispatcherWithAi($fs, $this->pairOutput, $this->specRunner, 'anthropic');

            $dispatcher->dispatch('write a spec for a Calculator');
            $output = $this->buffer->fetch();

            expect($output)->toContain('could not start');
            expect($output)->not()->toContain('Unknown command');
        });

        it('reports the provider as configured but unavailable in help', function (Filesystem $fs) {
            $dispatcher = commandDispatcherWithAi($fs, $this->pairOutput, $this->specRunner, 'anthropic');

            $dispatcher->dispatch('/help');
            $output = $this->buffer->fetch();

            expect($output)->toContain('unavailable');
            expect($output)->not()->toContain('(available)');
        });
    });

    context('/generate (AI-required)', function () {
        it('shows usage when /generate has no instruction', function () {
            $this->dispatcher->dispatch('/generate');
            expect($this->buffer->fetch())->toContain('Usage: /generate');
        });

        it('requires AI configuration', function () {
            $this->dispatcher->dispatch('/generate build a Calculator');
            expect($this->buffer->fetch())->toContain('AI configuration required');
        });

        it('shows a diff and writes the proposal once confirmed', function (Filesystem $fs) {
            $yamlPath = './phpspec.yaml';
            allow($fs->exists())->toReturnUsing(fn(string $p) => $p === $yamlPath);
            allow($fs->read())->toReturnUsing(fn(string $p) => $p === $yamlPath ? "ai:\n  provider: openai\n  api_key: k\n" : '');
            allow($fs->mkdir())->toReturn(null);
            $written = [];
            allow($fs->write())->toReturnUsing(function (string $p, string $c) use (&$written) {
                $written[$p] = $c;
            });

            $config = new Configuration('.', $fs);
            $replay = new ReplayProvider([
                new Response('', [new ToolCall('1', 'propose_edit', ['path' => 'src/App/Calc.php', 'content' => "<?php\nclass Calc {}"])]),
            ]);
            $dispatcher = new CommandDispatcher(
                new SpecGenerator('spec', $fs),
                new ClassGenerator('src', $fs),
                $config,
                $this->pairOutput,
                false,
                $fs,
                agent: new Agent($config, $fs, $replay),
            );

            $dispatcher->dispatch('/generate a Calc class');

            $out = $this->buffer->fetch();
            expect($out)->toContain('[NEW FILE]');
            expect($written[getcwd() . '/src/App/Calc.php'] ?? '')->toContain('class Calc');
        });
    });

    context('next-command suggestion (ghost hint)', function () {
        it('pre-fills a /generate ghost from the suggestion the AI registered on /next', function (Filesystem $fs) {
            allow($fs->exists())->toReturn(false);
            allow($fs->isDir())->toReturn(false);
            allow($fs->isFile())->toReturn(false);
            allow($fs->scandir())->toReturn([]);
            allow($fs->read())->toReturn('');

            $provider = new class implements ProviderInterface {
                public int $turn = 0;

                public function chat(array $messages, array $options = []): Response
                {
                    if (++$this->turn === 1) {
                        return new Response('', [new ToolCall('t1', 'suggest_next', [
                            'type' => 'example',
                            'target' => 'App\\TodoList',
                            'reason' => 'The current examples assert null.',
                        ])]);
                    }

                    return new Response('Let us make the example real.');
                }
            };

            $config = new Configuration('.', $fs);
            $assistant = new AiAssistant($provider, $config, $this->pairOutput, 'test-model', $fs, false, null, new Chooser($this->pairOutput, false), null, $this->specRunner);
            $dispatcher = new CommandDispatcher(
                new SpecGenerator('spec', $fs),
                new ClassGenerator('src', $fs),
                $config,
                $this->pairOutput,
                false,
                $fs,
                specRunner: $this->specRunner,
                ai: $assistant,
            );

            $dispatcher->dispatch('/next');

            expect($dispatcher->suggestion())->toBe('/generate add a spec example for App\\TodoList');
        });

        it('pre-fills a /refactor ghost from a refactor suggestion the AI registered on /next', function (Filesystem $fs) {
            allow($fs->exists())->toReturn(false);
            allow($fs->isDir())->toReturn(false);
            allow($fs->isFile())->toReturn(false);
            allow($fs->scandir())->toReturn([]);
            allow($fs->read())->toReturn('');

            $provider = new class implements ProviderInterface {
                public int $turn = 0;

                public function chat(array $messages, array $options = []): Response
                {
                    if (++$this->turn === 1) {
                        return new Response('', [new ToolCall('t1', 'suggest_next', [
                            'type' => 'refactor',
                            'target' => 'App\\TodoList',
                            'reason' => 'The suite is green; complete() and isComplete() duplicate the lookup.',
                        ])]);
                    }

                    return new Response('Green: time to clean up.');
                }
            };

            $config = new Configuration('.', $fs);
            $assistant = new AiAssistant($provider, $config, $this->pairOutput, 'test-model', $fs, false, null, new Chooser($this->pairOutput, false), null, $this->specRunner);
            $dispatcher = new CommandDispatcher(
                new SpecGenerator('spec', $fs),
                new ClassGenerator('src', $fs),
                $config,
                $this->pairOutput,
                false,
                $fs,
                specRunner: $this->specRunner,
                ai: $assistant,
            );

            $dispatcher->dispatch('/next');

            expect($dispatcher->suggestion())->toBe('/refactor App\\TodoList');
        });

        it('pre-fills a /generate ghost for steps and implement suggestions', function (Filesystem $fs) {
            allow($fs->exists())->toReturn(false);
            allow($fs->isDir())->toReturn(false);
            allow($fs->isFile())->toReturn(false);
            allow($fs->scandir())->toReturn([]);
            allow($fs->read())->toReturn('');

            $provider = new class implements ProviderInterface {
                public int $turn = 0;

                public function chat(array $messages, array $options = []): Response
                {
                    if (++$this->turn === 1) {
                        return new Response('', [new ToolCall('t1', 'suggest_next', [
                            'type' => 'steps',
                            'target' => 'features/basket.feature',
                            'reason' => 'The feature has undefined steps.',
                        ])]);
                    }

                    return new Response('The steps come next.');
                }
            };

            $config = new Configuration('.', $fs);
            $assistant = new AiAssistant($provider, $config, $this->pairOutput, 'test-model', $fs, false, null, new Chooser($this->pairOutput, false), null, $this->specRunner);
            $dispatcher = new CommandDispatcher(
                new SpecGenerator('spec', $fs),
                new ClassGenerator('src', $fs),
                $config,
                $this->pairOutput,
                false,
                $fs,
                specRunner: $this->specRunner,
                ai: $assistant,
            );

            $dispatcher->dispatch('/next');

            expect($dispatcher->suggestion())->toBe('/generate the steps for features/basket.feature');
        });

        it('suggests running the new spec after /describe', function (Filesystem $fs) {
            allow($fs->exists())->toReturn(false);
            allow($fs->mkdir())->toReturn(null);
            allow($fs->write())->toReturn(null);

            $this->dispatcher->dispatch('/describe App/Basket');

            expect($this->dispatcher->suggestion())->toBe('/run spec/App/Basket.spec.php');
        });

        it('suggests /next after /run', function () {
            $this->dispatcher->dispatch('/run');

            expect($this->dispatcher->suggestion())->toBe('/next');
        });

        it('suggests /run after /exemplify', function (Filesystem $fs) {
            allow($fs->exists())->toReturn(true);
            allow($fs->read())->toReturn("<?php\ndescribe(Calculator::class, function() {\n});");
            allow($fs->write())->toReturn(null);

            $this->dispatcher->dispatch('/exemplify App/Calc add');

            expect($this->dispatcher->suggestion())->toBe('/run');
        });

        it('clears the suggestion after a non-command prompt', function () {
            $this->dispatcher->dispatch('/run');
            $this->dispatcher->dispatch('hello there');

            expect($this->dispatcher->suggestion())->toBeNull();
        });
    });

    context('help output', function () {
        it('shows AI as not configured when no ai config', function () {
            $this->dispatcher->dispatch('/help');
            $output = $this->buffer->fetch();
            expect($output)->toContain('not configured');
        });
    });

    context('logging', function () {
        it('creates log file on dispatch', function () {
            $logFile = getcwd() . '/.phpspec/pair/session.log';
            if (file_exists($logFile)) {
                unlink($logFile);
            }

            $this->dispatcher->dispatch('/help');

            expect(file_exists($logFile))->toBeTrue();
            $contents = file_get_contents($logFile);
            expect($contents)->toContain('[CMD]');
            expect($contents)->toContain('/help');

            unlink($logFile);
        });
    });

    context('delegated application commands', function () {
        let('app', function () {
            $app = new Application('phpspec', '1.0');
            $app->setAutoExit(false);
            $app->{method_exists($app, 'addCommand') ? 'addCommand' : 'add'}(new \PhpSpec\Console\Command\Next(new Configuration('.')));
            $app->{method_exists($app, 'addCommand') ? 'addCommand' : 'add'}(new \PhpSpec\Console\Command\Refactor(new Configuration('.')));
            return $app;
        });
        let('appDispatcher', fn(Filesystem $fs) => new CommandDispatcher(
            new SpecGenerator('spec', $fs),
            new ClassGenerator('src', $fs),
            $this->config,
            $this->pairOutput,
            false,
            $fs,
            $this->app,
            specRunner: $this->specRunner,
        ));

        it('handles /next from suite state and returns CONTINUE', function () {
            $result = $this->appDispatcher->dispatch('/next');
            expect($result)->toBe(CommandDispatcher::CONTINUE);
        });

        it('delegates /refactor with its target actually bound', function () {
            $result = $this->appDispatcher->dispatch('/refactor App\\Calculator');
            $output = $this->buffer->fetch();
            expect($result)->toBe(CommandDispatcher::CONTINUE);
            // The target must reach the command: binding past the arguments and
            // into execute, which asks for the missing AI config by name.
            expect($output)->not()->toContain('Not enough arguments');
            expect($output)->toContain('AI configuration required');
        });

        it('delegates a bare "refactor Class" to the refactor command', function () {
            $result = $this->appDispatcher->dispatch('refactor App\\Calculator');
            $output = $this->buffer->fetch();
            expect($result)->toBe(CommandDispatcher::CONTINUE);
            expect($output)->not()->toContain('Not enough arguments');
            expect($output)->toContain('AI configuration required');
        });

        it('explains AI is off for non-slash prose when no AI', function () {
            $this->appDispatcher->dispatch('what should I build next');
            $output = $this->buffer->fetch();
            expect($output)->toContain('natural language');
            expect($output)->not()->toContain('Unknown command');
        });

        it('shows additional commands in help', function () {
            $this->appDispatcher->dispatch('/help');
            $output = $this->buffer->fetch();
            expect($output)->toContain('Additional commands');
            expect($output)->toContain('next');
            expect($output)->toContain('refactor');
        });

        it('does not list pair in additional commands', function () {
            $this->app->{method_exists($this->app, 'addCommand') ? 'addCommand' : 'add'}(new \PhpSpec\Console\Command\Pair(
                new SpecGenerator('spec'),
                new ClassGenerator('src'),
                new Configuration('.'),
            ));
            $this->appDispatcher->dispatch('/help');
            $output = $this->buffer->fetch();
            // pair should not appear in additional commands
            expect($output)->not()->toMatch('/Additional commands.*pair/s');
        });

        it('silently ignores /pair command input', function () {
            $this->app->{method_exists($this->app, 'addCommand') ? 'addCommand' : 'add'}(new \PhpSpec\Console\Command\Pair(
                new SpecGenerator('spec'),
                new ClassGenerator('src'),
                new Configuration('.'),
            ));
            $result = $this->appDispatcher->dispatch('/pair');
            expect($result)->toBe(CommandDispatcher::CONTINUE);
            $output = $this->buffer->fetch();
            expect($output)->not()->toContain('Unknown command');
        });

        it('shows error when a delegated command fails', function () {
            // /refactor without its required arg triggers an exception in bind
            $result = $this->appDispatcher->dispatch('/refactor');
            expect($result)->toBe(CommandDispatcher::CONTINUE);
            $output = $this->buffer->fetch();
            expect($output)->toContain('Not enough arguments');
        });
    });
});
