<?php

use PhpSpec\Filesystem;
use PhpSpec\Guard\Verdict;
use PhpSpec\Guard\Violation;
use Symfony\Component\Console\Output\BufferedOutput;

describe(Verdict::class, function () {

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

    it('holds when nothing was left unspecified', function (Filesystem $fs) {
        $verdict = new Verdict([], $fs, '/app');

        expect($verdict->held())->toBeTrue();
        expect($verdict->toArray())->toBe(['held' => true, 'violations' => []]);
    });

    it('says nothing to a reader when the cycle held', function (Filesystem $fs) {
        $output = new BufferedOutput();
        (new Verdict([], $fs, '/app'))->render($output);

        expect($output->fetch())->toBe('');
    });

    // The answer to "what did I fail to specify" is the code itself, so the
    // verdict shows it rather than describing where to look.
    it('shows the offending lines in what surrounds them', function (Filesystem $fs) use ($source) {
        allow($fs->exists())->toReturn(true);
        allow($fs->readLines())->toReturn(explode("\n", $source));

        $output = new BufferedOutput();
        $violation = new Violation('src/Basket.php', [7, 8], 'Basket::applyCoupon');
        (new Verdict([$violation], $fs, '/app'))->render($output);
        $written = $output->fetch();

        expect($written)->toContain('Guard Violation');
        expect($written)->toContain('if ($coupon->expired()) {');
        // Marked as added, and shown with a little of what came before.
        expect($written)->toContain('   7 +');
        expect($written)->toContain('   5  ');
        expect($written)->toContain('New logic in Basket::applyCoupon is untested.');
        expect($written)->toContain('Write an example for Basket::applyCoupon, then make it pass.');
    });

    it('hands the same verdict over as data', function (Filesystem $fs) {
        $verdict = new Verdict([new Violation('src/Basket.php', [7], 'Basket::applyCoupon')], $fs, '/app');

        expect($verdict->toArray())->toBe([
            'held' => false,
            'violations' => [[
                'file' => 'src/Basket.php',
                'lines' => [7],
                'member' => 'Basket::applyCoupon',
                'remedy' => 'Write an example for Basket::applyCoupon, then make it pass.',
            ]],
        ]);
    });

    it('points at the file when the source names no member', function (Filesystem $fs) {
        $violation = new Violation('src/helpers.php', [12]);

        expect($violation->summary())->toBe('New logic in src/helpers.php:12 is untested.');
        expect($violation->remedy())->toBe('Write an example that reaches src/helpers.php, then make it pass.');
    });

});
