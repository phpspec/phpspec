<?php

use PhpSpec\Ai\Agent\Agent;
use PhpSpec\Configuration;
use PhpSpec\Filesystem;

require_once __DIR__ . '/../ReplayProvider.php';

// E13 (eval, from the 2026-07-26 dogfood transcript) — "the body of the steps
// in features/completing_a_task.feature" when every step is already defined
// with a pending() stub. The deterministic scaffolder has nothing to add, so
// it must decline and the model must be consulted, grounded in BOTH the
// feature and the current steps file, answering with a propose_edit that
// carries real implementations. Before the fix this returned a no-op
// "MODIFIED" diff of unchanged pending() stubs without asking the model.
describe('E13 generate: step bodies come from the model when the scaffold is complete', function () {

    it('declines the no-op scaffold and edits the steps file with real bodies', function (Filesystem $fs) {
        $cwd = getcwd();
        $feature = "Feature: Completing a task\n\n  Scenario: Completing a task\n    Given a starting context\n    When something happens\n    Then the outcome is checked\n";
        $pendingSteps = "<?php\n\ngiven(\"a starting context\", function () {\n    pending();\n});\n\nwhen(\"something happens\", function () {\n    pending();\n});\n\nthen(\"the outcome is checked\", function () {\n    pending();\n});\n";
        $dirs = ["$cwd/features", "$cwd/features/steps"];
        $files = [
            "$cwd/features/completing_a_task.feature" => $feature,
            "$cwd/features/steps/completing_a_task.steps.php" => $pendingSteps,
        ];

        allow($fs->exists())->toReturnUsing(fn(string $p): bool => in_array($p, $dirs, true) || isset($files[$p]));
        allow($fs->isDir())->toReturnUsing(fn(string $p): bool => in_array($p, $dirs, true));
        allow($fs->isFile())->toReturnUsing(fn(string $p): bool => isset($files[$p]));
        allow($fs->read())->toReturnUsing(fn(string $p): string => $files[$p] ?? '');
        allow($fs->scandir())->toReturnUsing(fn(string $p): array => match (true) {
            str_ends_with($p, '/features') => ['completing_a_task.feature', 'steps'],
            str_ends_with($p, '/features/steps') => ['completing_a_task.steps.php'],
            default => [],
        });
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);

        $rec = json_decode((string) file_get_contents(__DIR__ . '/recordings/generate-steps-bodies.json'), true);
        $replay = ReplayProvider::fromRecording($rec);

        $agent = new Agent(new Configuration('.', $fs), $fs, $replay);
        $outcome = $agent->chat('generate', $rec['instruction']);

        $context = $replay->requests[0]['messages'][1]->content;
        expect($context)->toContain('pending()');                 // the current steps file reached the model
        expect($context)->toContain('Given a starting context');  // and the feature it serves

        expect($outcome->proposals[0]->path)->toBe('features/steps/completing_a_task.steps.php');
        expect($outcome->proposals[0]->isNew)->toBe(false);
        expect($outcome->proposals[0]->new)->not()->toContain('pending()');
        expect($outcome->proposals[0]->new)->toContain('expect(');
    });

});
