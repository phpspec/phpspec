<?php

use PhpSpec\FilterRegistry;
use PhpSpec\TitleFilter;

describe(FilterRegistry::class, function () {

    afterEach(function () {
        FilterRegistry::reset();
    });

    it('has no filter by default', function () {
        expect(FilterRegistry::current())->toBeNull();
    });

    it('exposes the filter once activated', function () {
        $filter = new TitleFilter('wanted');

        FilterRegistry::activate($filter);

        expect(FilterRegistry::current())->toBe($filter);
    });

    it('clears the filter on reset', function () {
        FilterRegistry::activate(new TitleFilter('wanted'));

        FilterRegistry::reset();

        expect(FilterRegistry::current())->toBeNull();
    });
});
