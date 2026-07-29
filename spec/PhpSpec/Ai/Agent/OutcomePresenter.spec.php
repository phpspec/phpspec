<?php

use PhpSpec\Ai\Agent\Outcome;
use PhpSpec\Ai\Agent\OutcomePresenter;
use PhpSpec\Ai\Agent\Phase;
use PhpSpec\Ai\Agent\Proposal;
use PhpSpec\Ai\Agent\Step;

describe(OutcomePresenter::class, function () {

    it('renders a next suggestion with the exact command that acts on it', function () {
        $outcome = new Outcome(
            new Step(Phase::Refactor, null, null, 'features are green: refactor, or grow the story'),
            [],
            '',
            ['type' => 'feature', 'target' => 'deleting_a_task', 'reason' => 'The list only grows.'],
        );

        $document = (new OutcomePresenter())->document('next', $outcome, [], 'vendor/bin/phpspec generate "a feature for deleting_a_task"');

        expect($document['command']['command'])->toBe('next');
        expect($document['suggestion']['type'])->toBe('feature');
        expect($document['suggestion']['target'])->toBe('deleting_a_task');
        expect($document['suggestion']['run'])->toBe('vendor/bin/phpspec generate "a feature for deleting_a_task"');
        expect($document['step']['phase'])->toBe('refactor');
        expect($document['actionable'])->toBe(1);
    });

    it('renders generate proposals as path, action, and applied, never file content', function () {
        $outcome = new Outcome(
            new Step(Phase::WriteFeature, 'features/adding.feature', null, 'you named it'),
            [
                new Proposal('features/adding.feature', '', "Feature: Adding\n", true, 'write_feature'),
                new Proposal('features/steps/adding.steps.php', 'old', 'new', false, 'write_steps'),
            ],
        );

        $document = (new OutcomePresenter())->document('generate', $outcome, [true, false]);

        expect($document['proposals'][0])->toBe(['path' => 'features/adding.feature', 'action' => 'create', 'applied' => true]);
        expect($document['proposals'][1])->toBe(['path' => 'features/steps/adding.steps.php', 'action' => 'update', 'applied' => false]);
        expect($document['actionable'])->toBe(1);
        expect((string) json_encode($document))->not()->toContain('Feature: Adding');
    });

    it('carries the prose and reports nothing actionable when there is nothing to act on', function () {
        $document = (new OutcomePresenter())->document('generate', new Outcome(null, [], 'The model returned no usable answer.'));

        expect($document['prose'])->toBe('The model returned no usable answer.');
        expect($document['actionable'])->toBe(0);
        expect(array_key_exists('step', $document))->toBe(false);
        expect(array_key_exists('suggestion', $document))->toBe(false);
    });

    it('emits one JSON line, raw and unescaped', function () {
        $json = (new OutcomePresenter())->render('next', new Outcome(null, [], 'a/b stays a/b'));

        expect($json)->toContain('"command":"next"');
        expect($json)->toContain('a/b stays a/b');
        expect($json)->not()->toContain('a\/b');
    });

});
