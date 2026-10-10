<?php

use PhpSpec\StoryBDD\StepsFile;

describe(StepsFile::class, function () {

    it('takes steps.php and any *.steps.php for step definitions, wherever they sit', function () {
        foreach (['steps.php', 'features/steps/steps.php', 'features/steps/web.steps.php', 'C:\project\features\steps\steps.php', '/abs/features/checkout/checkout.steps.php'] as $path) {
            expect((new StepsFile($path))->isStepDefinitions())->toBeTrue();
        }
    });

    it('takes no other php file for step definitions', function () {
        foreach (['features/steps/mysteps.php', 'features/steps/helpers.php', 'features/steps/steps.php.bak', 'src/Steps.php', 'features/steps/steps.phpx'] as $path) {
            expect((new StepsFile($path))->isStepDefinitions())->toBeFalse();
        }
    });
});
