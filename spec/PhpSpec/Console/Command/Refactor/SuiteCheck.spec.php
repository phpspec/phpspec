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

    it('keeps every failure inside the spec files named, and none outside them', function () {
        $check = SuiteCheck::fromStream(($this->stream)(
            ['event' => 'example', 'state' => 'error', 'spec' => 'spec/App/Checkout/LoyaltyDiscount.spec.php:7', 'message' => 'Class "App\Checkout\LoyaltyDiscount" not found'],
            ['event' => 'example', 'state' => 'failing', 'spec' => 'spec/App/Checkout/LoyaltyDiscount.spec.php:12', 'message' => 'Expected 10 to be 5'],
            ['event' => 'summary', 'examples' => 9, 'actionable' => 2],
        ));

        expect($check->green())->toBeFalse();
        expect($check->confinedTo(['spec/App/Checkout/LoyaltyDiscount.spec.php']))->toBeTrue();
        expect($check->confinedTo(['spec/App/Checkout/CheckoutService.spec.php']))->toBeFalse();
        expect($check->report())->toBe(
            "spec/App/Checkout/LoyaltyDiscount.spec.php:7  Class \"App\\Checkout\\LoyaltyDiscount\" not found\n"
            . "spec/App/Checkout/LoyaltyDiscount.spec.php:12  Expected 10 to be 5",
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
        expect($unplaced->report())->toBe('Checkout > later  pending');
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
