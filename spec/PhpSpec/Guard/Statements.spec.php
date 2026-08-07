<?php

use PhpSpec\Guard\Statements;

describe(Statements::class, function () {

    it('counts the lines that do something', function () {
        $lines = explode("\n", <<<'PHP'
        <?php

        namespace App;

        use App\Coupon;

        class Basket
        {
            public function total(): int
            {
                return 0;
            }
        }
        PHP);

        expect(Statements::in($lines))->toBe([11]);
    });

    it('says nothing about a comment or a docblock', function () {
        $lines = explode("\n", <<<'PHP'
        // a note
        # another
        /**
         * @return int
         */
        PHP);

        expect(Statements::in($lines))->toBe([]);
    });

    // Written on one line it is a declaration and a statement at once, and
    // reading only the first keyword made the whole method invisible: guard
    // had nothing to say about a class written that way and never specified.
    it('counts a method whose whole body is on the same line', function () {
        $lines = explode("\n", <<<'PHP'
        class Basket
        {
            public function total(): int { return 0; }
        }
        PHP);

        expect(Statements::in($lines))->toBe([3]);
    });

    it('says nothing about a body with nothing in it', function () {
        $lines = ['    public function __construct(private readonly int $total) {}'];

        expect(Statements::in($lines))->toBe([]);
    });

});
