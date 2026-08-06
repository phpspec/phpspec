<?php

use PhpSpec\Source\Members;

describe(Members::class, function () {

    beforeEach(function () {
        $this->basket = <<<'PHP'
            <?php

            namespace App;

            class Basket
            {
                public function total(): int
                {
                    return 0;
                }

                public function applyCoupon(int $value): int
                {
                    return $value;
                }
            }
            PHP;
    });

    it('names the member a line sits in', function () {
        $members = Members::in($this->basket);

        expect($members->at(13))->toBe('App\Basket::applyCoupon');
    });

    it('has no name for a line outside every member', function () {
        $members = Members::in($this->basket);

        expect($members->at(5))->toBeNull();
    });

    // A mutation testing tool mutates a method, so it needs the method's
    // boundaries: from the "function" keyword to the brace that closes it.
    it('spans each method from its signature to its closing brace', function () {
        $spans = Members::in($this->basket)->spans();

        expect($spans)->toBe([
            'total' => ['start' => 7, 'end' => 10],
            'applyCoupon' => ['start' => 12, 'end' => 15],
        ]);
    });

    it('leaves out a method that has no body to mutate', function () {
        $spans = Members::in(<<<'PHP'
            <?php

            interface Payable
            {
                public function pay(): void;
            }
            PHP)->spans();

        expect($spans)->toBe([]);
    });

    // Two classes in one file would otherwise share one key and one of them
    // would be handed the other's lines. A qualified name matches nothing
    // rather than matching the wrong thing.
    it('qualifies a name that two classes in the file both declare', function () {
        $spans = Members::in(<<<'PHP'
            <?php

            class Cash
            {
                public function pay(): void
                {
                }
            }

            class Card
            {
                public function pay(): void
                {
                }

                public function refund(): void
                {
                }
            }
            PHP)->spans();

        expect(array_keys($spans))->toBe(['Cash::pay', 'Card::pay', 'refund']);
    });

    it('reads a file it could never load', function () {
        $members = Members::in("<?php\n\nclass Broken\n{\n    public function go(): void\n    {\n        this is not php\n    }\n}\n");

        expect($members->at(7))->toBe('Broken::go');
    });

    it('says nothing about a source with no classes in it', function () {
        $members = Members::in("<?php\n\n\$total = 1 + 1;\n");

        expect($members->spans())->toBe([]);
        expect($members->at(3))->toBeNull();
    });

});
