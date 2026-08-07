<?php

use PhpSpec\Filesystem;
use PhpSpec\Guard\Report;
use PhpSpec\Guard\Verdict;
use PhpSpec\Guard\Violation;
use Symfony\Component\Console\Output\BufferedOutput;

describe(Report::class, function () {

    $source = <<<'PHP'
    <?php

    class Basket
    {
        public function applyCoupon($coupon)
        {
            if ($coupon->expired()) {
                return 0;
            }

            return $coupon->value();
        }
    }
    PHP;

    it('says nothing to a reader when the cycle held', function (Filesystem $fs) {
        $output = new BufferedOutput();

        (new Report($fs, '/app'))->render(Verdict::clean(), $output);

        expect($output->fetch())->toBe('');
    });

    // The answer to "what did I fail to specify" is the code itself, so the
    // report shows it rather than describing where to look.
    it('shows the offending lines in what surrounds them', function (Filesystem $fs) use ($source) {
        allow($fs->exists())->toReturn(true);
        allow($fs->readLines())->toReturn(explode("\n", $source));
        $verdict = Verdict::of([Violation::untestedLogic('src/Basket.php', [7, 8], 'Basket::applyCoupon')]);

        $output = new BufferedOutput();
        (new Report($fs, '/app'))->render($verdict, $output);
        $written = $output->fetch();

        expect($written)->toContain('Guard Violation');
        expect($written)->toContain('if ($coupon->expired()) {');
        // Marked as added, and shown with a little of what came before.
        expect($written)->toContain('   7 +');
        expect($written)->toContain('   5  ');
        expect($written)->toContain('New logic in Basket::applyCoupon is untested.');
        expect($written)->toContain('Write an example for Basket::applyCoupon, then make it pass.');
    });

    it('still says what went wrong when the file it named has gone', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        $verdict = Verdict::of([Violation::untestedLogic('src/Basket.php', [7], 'Basket::applyCoupon')]);

        $output = new BufferedOutput();
        (new Report($fs, '/app'))->render($verdict, $output);

        expect($output->fetch())->toContain('New logic in Basket::applyCoupon is untested.');
    });

});
