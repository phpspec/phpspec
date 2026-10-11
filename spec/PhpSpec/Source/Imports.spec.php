<?php

use PhpSpec\Source\Imports;

describe(Imports::class, function () {

    it('names the classes a file imports, plain and aliased, and nothing a closure or a trait uses', function () {
        $source = <<<'PHP'
            <?php

            namespace App\Checkout;

            use App\Pricing\Discount;
            use App\Customer\Customer as Buyer, Psr\Log\LoggerInterface;
            use function sprintf;
            use const PHP_EOL;
            use App\Shared\{Money, Currency};

            final class CheckoutService
            {
                use Loggable;

                public function total(): int
                {
                    return array_sum(array_map(function ($line) use ($rate) { return $line; }, []));
                }
            }
            PHP;

        expect((new Imports($source))->classes())->toBe(['App\\Pricing\\Discount', 'App\\Customer\\Customer', 'Psr\\Log\\LoggerInterface']);
    });

    it('names none for a file that imports nothing', function () {
        expect((new Imports("<?php\nclass Till {}\n"))->classes())->toBe([]);
    });
});
