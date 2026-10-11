<?php

use PhpSpec\Configuration;
use PhpSpec\Console\Command\Refactor;
use PhpSpec\Console\Command\Run\Generation;
use PhpSpec\Console\Consent;
use PhpSpec\Console\Prompt;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/../ReplayProvider.php';
require_once __DIR__ . '/../../Console/Command/Refactor/RefactorWorld.php';

// E14 (eval): a live refactoring of a pricer whose three primitives travel
// together, replayed through the command. The suite is read off the files the
// replayed steps write: every it() an example, a spec whose class does not
// exist yet in error. So what is graded is what the model wrote: the new
// collaborator described before it exists, nothing written outside src and
// spec, no example lost on the way, the subject left delegating.
describe('E14 refactor: a parameter object, spec first, replayed from a live session', function () {

    beforeEach(function () {
        $this->root = getcwd();
        $this->world = new RefactorWorldFilesystem();
        foreach (require __DIR__ . '/recordings/drink-pricer-project.php' as $path => $content) {
            $this->world->write($this->root . '/' . $path, $content);
        }
        $this->recording = json_decode((string) file_get_contents(__DIR__ . '/recordings/refactor-parameter-object.json'), true);
        $this->suite = new RefactorWorldSuite($this->world, $this->root);
        $this->refactor = function (): CommandTester {
            $this->replay = ReplayProvider::fromConversation($this->recording);
            $yes = new class extends Prompt {
                public function ask(string $prompt): ?string
                {
                    return 'y';
                }
            };
            $command = new Refactor(new Configuration(['ai' => ['provider' => 'google', 'api_key' => 'k']]), $this->world, $this->suite, $this->replay, new Consent('%s', Generation::Asks, prompt: $yes));
            $tester = new CommandTester($command);
            $tester->execute(['target' => 'Brew\\DrinkPricer']);

            return $tester;
        };
        $this->code = fn(string $path): string => $this->world->files[$this->root . '/' . $path] ?? '';
    });

    it('carries the plan out to the end, every step standing', function () {
        $tester = ($this->refactor)();

        expect($tester->getStatusCode())->toBe(0);
        expect($tester->getDisplay())->toContain('Refactoring done: Introduce Parameter Object ✓');
        expect(substr_count($tester->getDisplay(), 'Specs still pass ✓'))->toBe(4);
    });

    it('describes the new collaborator before it exists, red only in its own spec', function () {
        $tester = ($this->refactor)();

        expect($tester->getDisplay())->toContain('Red, as this step meant: spec/Brew/Drink.spec.php');
        $firstStep = $this->recording['turns'][0]['rounds'][1]['response']['tool_calls'][0]['arguments'];
        expect(array_column($firstStep['files'], 'path'))->toBe(['spec/Brew/Drink.spec.php']);
        expect($firstStep['red'])->toBeTrue();
    });

    it('writes nothing outside the source and spec paths, and loses no example on the way', function () {
        ($this->refactor)();

        $written = array_map(fn(string $path): string => substr($path, strlen($this->root) + 1), array_keys($this->world->files));
        expect(array_values(array_filter($written, fn(string $path): bool => !str_starts_with($path, 'src/') && !str_starts_with($path, 'spec/') && !str_starts_with($path, '.phpspec/'))))->toBe([]);
        expect($this->suite->examples)->toBe([4, 5, 5, 5, 6, 6]);
    });

    it('leaves the subject delegating to the parameter object, its old signature gone', function () {
        ($this->refactor)();

        expect(($this->code)('src/Brew/DrinkPricer.php'))->not()->toContain('function priceOf(');
        expect(($this->code)('src/Brew/DrinkPricer.php'))->toContain('return $drink->price();');
        expect(($this->code)('src/Brew/Drink.php'))->toContain('public function price(): Money');
    });

    it('asks for the plan and for each step through a forced tool call', function () {
        ($this->refactor)();

        $choices = array_map(fn(array $request): mixed => $request['options']['toolChoice'], $this->replay->requests);
        expect($choices)->toBe(['required', ...array_fill(0, 5, ['name' => 'propose_step'])]);
    });
});
