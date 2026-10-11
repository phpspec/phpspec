<?php

use PhpSpec\Configuration;
use PhpSpec\Console\Command\Refactor;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/../ReplayProvider.php';
require_once __DIR__ . '/../../Console/Command/Refactor/RefactorWorld.php';

// E16 (eval): a live refactoring run with --no-interaction on the pricer,
// replayed through the command. Nobody is asked, so what is graded is what
// the model did on its own: each collaborator described before it exists,
// red only in that spec, no example lost, and the subject left delegating.
describe('E16 refactor: three collaborators extracted spec first with nobody asked, replayed from a live session', function () {

    beforeEach(function () {
        $this->root = getcwd();
        $this->world = new RefactorWorldFilesystem();
        foreach (require __DIR__ . '/recordings/drink-pricer-project.php' as $path => $content) {
            $this->world->write($this->root . '/' . $path, $content);
        }
        $this->suite = new RefactorWorldSuite($this->world, $this->root);
        $this->recording = json_decode((string) file_get_contents(__DIR__ . '/recordings/refactor-extract-class-unattended.json'), true);
        $this->steps = array_map(fn(array $round): array => $round['response']['tool_calls'][0]['arguments'], array_slice($this->recording['turns'][0]['rounds'], 1));
        $this->refactor = function (): CommandTester {
            $command = new Refactor(new Configuration(['ai' => ['provider' => 'google', 'api_key' => 'k']]), $this->world, $this->suite, ReplayProvider::fromConversation($this->recording));
            $tester = new CommandTester($command);
            $tester->execute(['target' => 'Brew\\DrinkPricer'], ['interactive' => false]);

            return $tester;
        };
        $this->code = fn(string $path): string => $this->world->files[$this->root . '/' . $path] ?? '';
    });

    it('carries every step out without a question, the plan and the diffs unshown', function () {
        $tester = ($this->refactor)();
        $display = $tester->getDisplay();

        expect($tester->getStatusCode())->toBe(0);
        expect($display)->toContain('Refactoring done: Extract Class ✓');
        expect($display)->not()->toContain('[Y/n]');
        expect($display)->not()->toContain('Plan:');
        expect(substr_count($display, 'Specs still pass ✓'))->toBe(3);
        expect(substr_count($display, 'Red, as this step meant:'))->toBe(3);
    });

    it('describes each collaborator in a step of its own before the step that writes it', function () {
        $written = array_map(fn(array $step): array => array_column($step['files'], 'path'), $this->steps);

        foreach (['Size', 'Milk', 'Shots'] as $index => $collaborator) {
            expect($written[$index * 2])->toBe(["spec/Brew/$collaborator.spec.php"]);
            expect($this->steps[$index * 2]['red'])->toBeTrue();
            expect($written[$index * 2 + 1])->toContain("src/Brew/$collaborator.php");
        }
    });

    it('loses no example on the way, and leaves the subject delegating to its collaborators', function () {
        ($this->refactor)();

        expect($this->suite->examples)->toBe([4, 8, 8, 12, 12, 14, 14]);
        expect(($this->code)('src/Brew/DrinkPricer.php'))->toContain('(new Size($size))');
        expect(($this->code)('src/Brew/DrinkPricer.php'))->not()->toContain("'oat'");
    });
});
