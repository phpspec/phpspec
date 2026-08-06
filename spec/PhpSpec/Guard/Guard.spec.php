<?php

use PhpSpec\Filesystem;
use PhpSpec\Guard\Coverage;
use PhpSpec\Guard\Delta;
use PhpSpec\Guard\Guard;

describe(Guard::class, function () {

    $source = <<<'PHP'
    <?php

    namespace App;

    class Basket
    {
        public function total(): int
        {
            return 0;
        }

        public function applyCoupon(Coupon $coupon): int
        {
            if ($coupon->expired()) {
                return 0;
            }

            return $coupon->value();
        }
    }
    PHP;

    $file = function (Filesystem $fs, string $source): void {
        allow($fs->exists())->toReturn(true);
        allow($fs->readLines())->toReturn(explode("\n", $source));
    };

    // Changed and covered is fine: that is a refactor, and it needs no mode of
    // its own.
    it('has nothing to say about changed lines an example reaches', function (Filesystem $fs) use ($source, $file) {
        $file($fs, $source);
        $coverage = new Coverage(['src/Basket.php' => [9 => 3, 14 => 2, 15 => 1, 18 => 2]]);

        $violations = (new Guard($fs, '/app'))->violations(Delta::of(['src/Basket.php' => [9, 14]]), $coverage);

        expect($violations)->toBe([]);
    });

    it('names the member the untested logic sits in', function (Filesystem $fs) use ($source, $file) {
        $file($fs, $source);
        // The run loaded the file and reached total(), but never the branch.
        $coverage = new Coverage(['src/Basket.php' => [9 => 3, 14 => -1, 15 => -1, 18 => -1]]);

        $violations = (new Guard($fs, '/app'))->violations(Delta::of(['src/Basket.php' => [14, 15]]), $coverage);

        expect($violations)->toHaveCount(1);
        expect($violations[0]->member)->toBe('App\\Basket::applyCoupon');
        expect($violations[0]->lines)->toBe([14, 15]);
        expect($violations[0]->summary())->toBe('New logic in App\\Basket::applyCoupon is untested.');
        expect($violations[0]->remedy())->toBe('Write an example for App\\Basket::applyCoupon, then make it pass.');
    });

    // A brace, a blank line and a declaration are not logic. Without this
    // guard would cry wolf on every change.
    it('says nothing about lines that are not executable', function (Filesystem $fs) use ($source, $file) {
        $file($fs, $source);
        $coverage = new Coverage(['src/Basket.php' => [5 => -2, 6 => -2, 7 => -2, 9 => 3]]);

        $violations = (new Guard($fs, '/app'))->violations(Delta::of(['src/Basket.php' => [5, 6, 7]]), $coverage);

        expect($violations)->toBe([]);
    });

    // A class written and never specified is the shape guard exists for: the
    // run never loaded the file, so coverage has nothing to say about it.
    it('reads the source when the run never loaded the file at all', function (Filesystem $fs) use ($source, $file) {
        $file($fs, $source);

        $violations = (new Guard($fs, '/app'))->violations(
            Delta::of(['src/Basket.php' => range(1, 20)]),
            Coverage::nothing(),
        );

        expect($violations)->not()->toBe([]);
        // The declarations and braces are not counted; the statements are.
        $lines = array_merge(...array_map(fn($violation) => $violation->lines, $violations));
        expect($lines)->toBe([9, 14, 15, 18]);
    });

    it('reports each member separately, so one report is one thing to fix', function (Filesystem $fs) use ($source, $file) {
        $file($fs, $source);
        $coverage = new Coverage(['src/Basket.php' => [9 => -1, 14 => -1, 18 => -1]]);

        $violations = (new Guard($fs, '/app'))->violations(Delta::of(['src/Basket.php' => [9, 14, 18]]), $coverage);

        expect(array_map(fn($violation) => $violation->member, $violations))
            ->toBe(['App\\Basket::total', 'App\\Basket::applyCoupon']);
    });

    it('has nothing to say when the session changed nothing', function (Filesystem $fs) {
        expect((new Guard($fs, '/app'))->violations(Delta::nothing(), Coverage::nothing()))->toBe([]);
    });

});
