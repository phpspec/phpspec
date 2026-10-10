<?php

use PhpSpec\Console\Command\Refactor\SuiteCheck;

describe(SuiteCheck::class, function () {

    let('stream', fn() => fn(array ...$events): string => implode("\n", array_map(fn(array $event): string => json_encode(['v' => 2] + $event), $events)) . "\n");

    it('is green when the summary leaves nothing to act on', function () {
        $check = SuiteCheck::fromStream(($this->stream)(
            ['event' => 'run_started'],
            ['event' => 'summary', 'examples' => 3, 'actionable' => 0],
        ));

        expect($check->green())->toBeTrue();
        expect($check->confinedTo([]))->toBeTrue();
        expect($check->report())->toBe('');
    });

    it('reads an entry the run reported but does not count against it as no failure: skipped, risky, or passing with a note', function () {
        $check = SuiteCheck::fromStream(($this->stream)(
            ['event' => 'example', 'state' => 'skipped', 'example' => 'Till > on PHP 8.5 only', 'spec' => 'spec/App/Till.spec.php:4'],
            ['event' => 'example', 'state' => 'risky', 'example' => 'Till > expects nothing', 'spec' => 'spec/App/Till.spec.php:8'],
            ['event' => 'example', 'state' => 'passing', 'example' => 'Till > rings up', 'spec' => 'spec/App/Till.spec.php:12', 'deprecations' => [['message' => 'Implicitly nullable', 'at' => 'src/App/Till.php:9']]],
            ['event' => 'summary', 'examples' => 3, 'actionable' => 0],
        ));

        expect($check->green())->toBeTrue();
        expect($check->report())->toBe('');
    });

    it('says why it is red when nothing failed but the run still left work to do', function () {
        $check = SuiteCheck::fromStream(($this->stream)(
            ['event' => 'summary', 'examples' => 3, 'actionable' => 2, 'coverage' => ['percent' => 81.3, 'required' => 90, 'met' => false], 'guard' => ['held' => false, 'judged' => true, 'violations' => [['file' => 'src/App/Till.php', 'lines' => [14, 15], 'member' => 'App\\Till::refund', 'remedy' => 'Write an example that reaches App\\Till::refund.']]]],
        ));

        expect($check->green())->toBeFalse();
        expect($check->confinedTo(['spec/App/Till.spec.php']))->toBeFalse();
        expect($check->report())->toBe(
            "src/App/Till.php:14  Write an example that reaches App\\Till::refund.\n"
            . 'Coverage is 81.3%, under the 90% required.',
        );
    });

    it('keeps every failure inside the spec files named, and none outside them', function () {
        $check = SuiteCheck::fromStream(($this->stream)(
            ['event' => 'example', 'state' => 'error', 'example' => 'LoyaltyDiscount > discounts', 'spec' => 'spec/App/Checkout/LoyaltyDiscount.spec.php:7', 'message' => 'Class "App\Checkout\LoyaltyDiscount" not found'],
            ['event' => 'example', 'state' => 'failing', 'example' => 'LoyaltyDiscount > caps', 'spec' => 'spec/App/Checkout/LoyaltyDiscount.spec.php:12', 'message' => 'Expected 10 to be 5'],
            ['event' => 'summary', 'examples' => 9, 'actionable' => 2],
        ));

        expect($check->green())->toBeFalse();
        expect($check->confinedTo(['spec/App/Checkout/LoyaltyDiscount.spec.php']))->toBeTrue();
        expect($check->confinedTo(['spec/App/Checkout/CheckoutService.spec.php']))->toBeFalse();
        expect($check->report())->toBe(
            "spec/App/Checkout/LoyaltyDiscount.spec.php:7  LoyaltyDiscount > discounts: Class \"App\\Checkout\\LoyaltyDiscount\" not found\n"
            . "spec/App/Checkout/LoyaltyDiscount.spec.php:12  LoyaltyDiscount > caps: Expected 10 to be 5",
        );
    });

    it('keeps no failure it cannot place, nor a run that died, inside any file', function () {
        $unplaced = SuiteCheck::fromStream(($this->stream)(
            ['event' => 'example', 'state' => 'pending', 'example' => 'Checkout > later'],
            ['event' => 'summary', 'actionable' => 1],
        ));
        $died = SuiteCheck::fromStream(($this->stream)(
            ['event' => 'fatal', 'message' => 'Cannot redeclare class App\Till'],
            ['event' => 'summary', 'actionable' => 1],
        ));

        expect($unplaced->confinedTo(['spec/App/Checkout.spec.php']))->toBeFalse();
        expect($unplaced->report())->toBe('Checkout > later: pending');
        expect($died->confinedTo(['spec/App/Till.spec.php']))->toBeFalse();
        expect($died->report())->toBe('Cannot redeclare class App\Till');
    });

    it('is red, saying what it got, when what came back is no run at all', function () {
        $check = SuiteCheck::fromStream('', 'PHP Parse error: syntax error in src/App/Till.php on line 3');

        expect($check->green())->toBeFalse();
        expect($check->confinedTo([]))->toBeFalse();
        expect($check->report())->toBe('PHP Parse error: syntax error in src/App/Till.php on line 3');
    });
});
