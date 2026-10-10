<?php

use PhpSpec\Result\StepResult;
use PhpSpec\Specification\PendingException;
use PhpSpec\Specification\SkippedException;
use PhpSpec\StoryBDD\HookOutcome;

describe(HookOutcome::class, function () {

    it('puts a before-hook signal on the step it kept from running, with the reason', function () {
        $skipped = (new HookOutcome(new SkippedException('no service'), 'beforeScenario'))->instead('Given a step');
        $pending = (new HookOutcome(new PendingException('later'), 'beforeStep'))->instead('Given a step');

        expect($skipped->isSkipped())->toBeTrue();
        expect($skipped->getReason())->toBe('no service');
        expect($pending->isPending())->toBeTrue();
        expect($pending->getReason())->toBe('later');
    });

    it('errors the step a before-hook kept from running, naming the hook', function () {
        $step = (new HookOutcome(new RuntimeException('database down'), 'beforeScenario'))->instead('Given a step');

        expect($step->isError())->toBeTrue();
        expect($step->getError()->getMessage())->toBe('database down (in beforeScenario)');
        expect($step->getError()->getType())->toBe(RuntimeException::class);
    });

    it('errors the step an after-hook followed, keeping what the step printed and how long it took', function () {
        $passed = new StepResult('Given a step', 'passed');
        $passed->setOutput('printed');
        $passed->setDuration(0.5);
        $passed->raised([['severity' => E_USER_DEPRECATED, 'message' => 'old', 'file' => 'a.php', 'line' => 3]]);

        $step = (new HookOutcome(new RuntimeException('log full'), 'afterStep'))->after($passed);

        expect($step->isError())->toBeTrue();
        expect($step->getError()->getMessage())->toBe('log full (in afterStep)');
        expect($step->getOutput())->toBe('printed');
        expect($step->getDuration())->toBe(0.5);
        expect($step->getDeprecations()[0]['message'])->toBe('old');
    });

    it('keeps the failure of a step that had already failed, warning with the hook error under it', function () {
        $failed = new StepResult('Given a step', 'failure');

        $step = (new HookOutcome(new RuntimeException('log full'), 'afterStep'))->after($failed);

        expect($step->isFailure())->toBeTrue();
        expect($step->getWarnings()[0]['message'])->toBe('log full (in afterStep)');
    });

    it('says an after-hook signal came too late', function () {
        $step = (new HookOutcome(new SkippedException('no service'), 'afterScenario'))->after(new StepResult('Given a step', 'passed'));

        expect($step->getError()->getMessage())->toBe('skip() in afterScenario comes after the scenario ran; call it in beforeScenario or in a step.');
    });

    it('stands alone, named after its hook, when there is no step to carry it', function () {
        expect((new HookOutcome(new RuntimeException('database down'), 'beforeScenario'))->alone()->getTitle())->toBe('beforeScenario');
    });
});
