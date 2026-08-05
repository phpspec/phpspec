<?php

use PhpSpec\Offers\Offer;

describe(Offer::class, function () {

    it('identifies itself from what it would do, so the same offer keeps its id', function () {
        $one = Offer::write('src/App/Basket.php', "<?php\nclass Basket {}", true, '');
        $again = Offer::write('src/App/Basket.php', "<?php\nclass Basket {}", true, '');

        expect($one->id)->toBe($again->id);
        expect($one->id)->toStartWith('o_');
    });

    it('is a different offer when the content differs', function () {
        $one = Offer::write('src/App/Basket.php', "<?php\nclass Basket {}", true, '');
        $other = Offer::write('src/App/Basket.php', "<?php\nfinal class Basket {}", true, '');

        expect($one->id)->not()->toBe($other->id);
    });

    it('is a different offer when the path differs', function () {
        $one = Offer::write('src/App/Basket.php', '<?php', true, '');
        $other = Offer::write('src/App/Cart.php', '<?php', true, '');

        expect($one->id)->not()->toBe($other->id);
    });

    it('says what it would do to the file', function () {
        expect(Offer::write('src/App/Basket.php', '<?php', true, '')->action)->toBe('create');
        expect(Offer::write('src/App/Basket.php', '<?php', false, 'old')->action)->toBe('update');
    });

    it('survives a round trip through storage', function () {
        $offer = Offer::write('src/App/Basket.php', "<?php\nclass Basket {}", false, 'the old content');

        expect(Offer::fromArray($offer->toArray())->toArray())->toBe($offer->toArray());
    });

    context('staleness', function () {
        it('stands while the file is as it was when offered', function () {
            $offer = Offer::write('src/App/Basket.php', '<?php new', false, 'the old content');

            expect($offer->staleAgainst('the old content'))->toBeFalse();
        });

        it('is stale once the file has moved on', function () {
            $offer = Offer::write('src/App/Basket.php', '<?php new', false, 'the old content');

            expect($offer->staleAgainst('someone edited it'))->toBeTrue();
        });

        it('is stale when what it would create is already there', function () {
            $offer = Offer::write('src/App/Basket.php', '<?php new', true, '');

            expect($offer->staleAgainst('something exists now'))->toBeTrue();
            expect($offer->staleAgainst(null))->toBeFalse();
        });

        it('is stale when what it would update has gone', function () {
            $offer = Offer::write('src/App/Basket.php', '<?php new', false, 'the old content');

            expect($offer->staleAgainst(null))->toBeTrue();
        });
    });
});
