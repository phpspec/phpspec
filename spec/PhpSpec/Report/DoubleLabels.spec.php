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

interface DoubleLabelsSpecFailure extends Throwable {}

interface DoubleLabelsSpecBag extends Traversable {}

interface DoubleLabelsSpecOrder {
    public function createdAt(): DateTimeInterface;

    public function failure(): DoubleLabelsSpecFailure;

    public function bag(): DoubleLabelsSpecBag;

    public function find($id);

    public function maybe(): ?DoubleLabelsSpecPage;

    public function either(): DoubleLabelsSpecPage|DoubleLabelsSpecBook;

    public function both(): DoubleLabelsSpecPage&DoubleLabelsSpecBook;

    public function bothOrIndex(): (DoubleLabelsSpecPage&DoubleLabelsSpecBook)|DoubleLabelsSpecIndex;
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

    it('names what a double answered by the type its call declares, never the base it is built on', function () {
        $order = mock(DoubleLabelsSpecOrder::class);
        $library = mock(DoubleLabelsSpecLibrary::class);

        expect(DoubleLabels::of($order->createdAt()))->toBe('DateTimeInterface double #3 (from DoubleLabelsSpecOrder double #1->createdAt())');
        expect(DoubleLabels::of($order->failure()))->toBe('DoubleLabelsSpecFailure double #4 (from DoubleLabelsSpecOrder double #1->failure())');
        expect(DoubleLabels::of($order->bag()))->toBe('DoubleLabelsSpecBag double #5 (from DoubleLabelsSpecOrder double #1->bag())');
        expect(DoubleLabels::of($order->find(1)))->toBe('mixed double #6 (from DoubleLabelsSpecOrder double #1->find())');
        expect(DoubleLabels::of($library->shelf()))->toBe('DoubleLabelsSpecShelf double #7 (from DoubleLabelsSpecLibrary double #2->shelf())');
        expect(DoubleLabels::of($library->index()))->toBe('DoubleLabelsSpecIndex double #8 (from DoubleLabelsSpecLibrary double #2->index())');
    });

    it('names it by a nullable type without its null, and by a union or an intersection as declared, whatever double answered', function () {
        $order = mock(DoubleLabelsSpecOrder::class);
        $dummy = dummy(DoubleLabelsSpecOrder::class);

        expect(DoubleLabels::of($order->maybe()))->toBe('DoubleLabelsSpecPage double #3 (from DoubleLabelsSpecOrder double #1->maybe())');
        expect(DoubleLabels::of($order->either()))->toBe('DoubleLabelsSpecPage|DoubleLabelsSpecBook double #4 (from DoubleLabelsSpecOrder double #1->either())');
        expect(DoubleLabels::of($order->both()))->toBe('DoubleLabelsSpecPage&DoubleLabelsSpecBook double #5 (from DoubleLabelsSpecOrder double #1->both())');
        expect(DoubleLabels::of($dummy->either()))->toBe('DoubleLabelsSpecPage|DoubleLabelsSpecBook double #6 (from DoubleLabelsSpecOrder double #2->either())');
        expect(DoubleLabels::of($order->bothOrIndex()))->toBe('(DoubleLabelsSpecPage&DoubleLabelsSpecBook)|DoubleLabelsSpecIndex double #7 (from DoubleLabelsSpecOrder double #1->bothOrIndex())');
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
