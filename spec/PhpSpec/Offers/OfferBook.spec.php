<?php

use PhpSpec\Filesystem;
use PhpSpec\Offers\Offer;
use PhpSpec\Offers\OfferBook;

describe(OfferBook::class, function () {

    beforeEach(function (Filesystem $fs) {
        $this->stored = null;
        allow($fs->exists())->toReturn(false);
        allow($fs->read())->toReturnUsing(fn(): string => $this->stored ?? '');
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturnUsing(function (string $path, string $content) {
            $this->stored = $content;
            allow($this->fs->exists())->toReturn(true);
        });
        $this->fs = $fs;
    });

    it('hands back an offer it recorded', function (Filesystem $fs) {
        $book = new OfferBook($fs);
        $offer = Offer::write('src/App/Basket.php', '<?php', true, '');

        $book->record($offer);

        expect((new OfferBook($fs))->find($offer->id)?->target)->toBe('src/App/Basket.php');
    });

    it('knows nothing of an id it never recorded', function (Filesystem $fs) {
        expect((new OfferBook($fs))->find('o_nothing'))->toBeNull();
    });

    it('keeps offers from earlier runs, so a reader can still accept one', function (Filesystem $fs) {
        $book = new OfferBook($fs);
        $first = Offer::write('src/App/Basket.php', '<?php', true, '');
        $second = Offer::write('src/App/Cart.php', '<?php', true, '');

        $book->record($first);
        (new OfferBook($fs))->record($second);

        $book = new OfferBook($fs);
        expect($book->find($first->id))->not()->toBeNull();
        expect($book->find($second->id))->not()->toBeNull();
    });

    it('records the same offer once, however often it is offered', function (Filesystem $fs) {
        $offer = Offer::write('src/App/Basket.php', '<?php', true, '');

        (new OfferBook($fs))->record($offer);
        (new OfferBook($fs))->record($offer);

        expect(json_decode($this->stored, true)['offers'])->toHaveLength(1);
    });

    it('forgets the oldest once the book is full, so it cannot grow without end', function (Filesystem $fs) {
        $book = new OfferBook($fs);
        $offers = [];
        // More than the book keeps, so the oldest must fall off the table.
        for ($i = 0; $i < 60; $i++) {
            $offers[] = Offer::write("src/App/Class$i.php", '<?php', true, '');
        }

        foreach ($offers as $offer) {
            (new OfferBook($fs))->record($offer);
        }

        $book = new OfferBook($fs);
        expect($book->find($offers[0]->id))->toBeNull();
        expect($book->find($offers[count($offers) - 1]->id))->not()->toBeNull();
    });

    it('survives a book that was damaged on disk', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn('not json at all');

        expect((new OfferBook($fs))->find('o_anything'))->toBeNull();
    });
});
