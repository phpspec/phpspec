<?php

use PhpSpec\Report\DoubleLabels;

interface DoubleLabelsSpecPage {
    public function next(): DoubleLabelsSpecPage;
}

interface DoubleLabelsSpecBook {
    public function first(): DoubleLabelsSpecPage;
}

interface DoubleLabelsSpecIndex extends Countable {}

class DoubleLabelsSpecShelf {}

interface DoubleLabelsSpecLibrary {
    public function index(): DoubleLabelsSpecIndex;

    public function shelf(): DoubleLabelsSpecShelf;
}

describe(DoubleLabels::class, function () {
    beforeEach(function () {
        $this->outer = DoubleLabels::save();
        DoubleLabels::start();
    });

    afterEach(function () {
        DoubleLabels::restore($this->outer);
    });

    it('names a double by its type and the number it was made with, whichever is shown first', function () {
        $first = mock(DoubleLabelsSpecPage::class);
        $second = dummy(DoubleLabelsSpecPage::class);

        expect(DoubleLabels::of($second))->toBe('DoubleLabelsSpecPage double #2');
        expect(DoubleLabels::of($first))->toBe('DoubleLabelsSpecPage double #1');
        expect(DoubleLabels::of(mock(DoubleLabelsSpecShelf::class)))->toBe('DoubleLabelsSpecShelf double #3');
    });

    it('names a double by the parameter names it was handed under, each once', function () {
        $page = mock(DoubleLabelsSpecPage::class);
        DoubleLabels::made($page, 'page');
        DoubleLabels::made($page, 'page');
        DoubleLabels::made($page, 'current');

        expect(DoubleLabels::of($page))->toBe('DoubleLabelsSpecPage double #1 ($page, $current)');
    });

    it('numbers a double it never saw made when first shown, after those made', function () {
        $made = mock(DoubleLabelsSpecPage::class);
        DoubleLabels::start();
        $later = mock(DoubleLabelsSpecPage::class);

        expect(DoubleLabels::of($made))->toBe('DoubleLabelsSpecPage double #2');
        expect(DoubleLabels::of($later))->toBe('DoubleLabelsSpecPage double #1');
    });

    it('names what a double answered by where it came from, numbered when first shown', function () {
        $book = dummy(DoubleLabelsSpecBook::class);
        $mock = mock(DoubleLabelsSpecBook::class);
        $page = mock(DoubleLabelsSpecPage::class);

        expect(DoubleLabels::of($book->first()->next()))->toBe('DoubleLabelsSpecPage double #4 (from DoubleLabelsSpecBook double #1->first()->next())');
        expect(DoubleLabels::of($mock->first()))->toBe('DoubleLabelsSpecPage double #5 (from DoubleLabelsSpecBook double #2->first())');
        expect(DoubleLabels::of($page))->toBe('DoubleLabelsSpecPage double #3');
    });

    it('names what a mock answered by the class it extends, or else the interfaces it implements that no other one names', function () {
        $library = mock(DoubleLabelsSpecLibrary::class);

        expect(DoubleLabels::of($library->shelf()))->toBe('DoubleLabelsSpecShelf double #2 (from DoubleLabelsSpecLibrary double #1->shelf())');
        expect(DoubleLabels::of($library->index()))->toBe('DoubleLabelsSpecIndex double #3 (from DoubleLabelsSpecLibrary double #1->index())');
    });

    it('starts each book at one, and puts a saved book back whole, its count included', function () {
        $outer = mock(DoubleLabelsSpecPage::class);
        $saved = DoubleLabels::save();

        DoubleLabels::start();
        $inner = mock(DoubleLabelsSpecPage::class);
        expect(DoubleLabels::of($inner))->toBe('DoubleLabelsSpecPage double #1');

        DoubleLabels::restore($saved);
        expect(DoubleLabels::of($outer))->toBe('DoubleLabelsSpecPage double #1');
        expect(DoubleLabels::of(mock(DoubleLabelsSpecPage::class)))->toBe('DoubleLabelsSpecPage double #2');
    });
});
