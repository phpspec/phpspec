<?php

use PhpSpec\TitleFilter;

describe(TitleFilter::class, function () {

    it('matches a title by case-insensitive substring', function () {
        $filter = new TitleFilter('WANTED');

        expect($filter->matches('does the wanted thing'))->toBeTrue();
        expect($filter->matches('does another thing'))->toBeFalse();
    });

    it('ignores a leading "it" on the filter text', function () {
        $filter = new TitleFilter('it should be good');

        expect($filter->matches('should be good'))->toBeTrue();
    });

    it('matches every title once the current spec path matches', function () {
        $filter = new TitleFilter('Targeted');

        $filter->beginSpec('spec/App/Targeted.spec.php');
        expect($filter->matches('anything at all'))->toBeTrue();

        $filter->beginSpec('spec/App/Excluded.spec.php');
        expect($filter->matches('anything at all'))->toBeFalse();
    });

    it('matches a title through the contexts it sits in, as a path joined by >', function () {
        $filter = new TitleFilter('when empty > starts');
        $filter->enterContext('Basket');
        $filter->enterContext('when empty');

        expect($filter->matches('starts out at zero'))->toBeTrue();
        expect($filter->matches('refuses a coupon'))->toBeFalse();

        $filter->leaveContext();
        expect($filter->matches('starts out at zero'))->toBeFalse();
    });

    it('selects everything inside a context whose title matches', function () {
        $filter = new TitleFilter('when empty');
        $filter->enterContext('Basket');
        expect($filter->matches('starts out at zero'))->toBeFalse();

        $filter->enterContext('when empty');
        expect($filter->matches('starts out at zero'))->toBeTrue();
        expect($filter->matches('refuses a coupon'))->toBeTrue();
    });

    it('matches paths by case-insensitive substring', function () {
        $filter = new TitleFilter('greeting');

        expect($filter->matchesPath('features/Greeting.feature'))->toBeTrue();
        expect($filter->matchesPath('features/other.feature'))->toBeFalse();
    });
});
