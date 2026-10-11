<?php

use PhpSpec\Console\Command\Refactor\PlannedStep;
use PhpSpec\Console\Command\Refactor\RefactorPlan;

describe(RefactorPlan::class, function () {

    it('survives being kept as data: the technique, why, and each step with how it reads under way', function () {
        $plan = new RefactorPlan('Extract Class', 'Till prints its own receipt.', [new PlannedStep('Describe Printer', 'Describing Printer')], 900, ['src/App/Till.php' => sha1('<?php')]);

        expect($plan->toArray())->toBe([
            'technique' => 'Extract Class',
            'rationale' => 'Till prints its own receipt.',
            'steps' => [['title' => 'Describe Printer', 'doing' => 'Describing Printer']],
        ]);
        expect(RefactorPlan::fromArray($plan->toArray()))->toBeLike(new RefactorPlan('Extract Class', 'Till prints its own receipt.', [new PlannedStep('Describe Printer', 'Describing Printer')]));
    });
});
