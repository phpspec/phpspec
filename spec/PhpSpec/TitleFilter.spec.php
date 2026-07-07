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

    it('matches paths by case-insensitive substring', function () {
        $filter = new TitleFilter('greeting');

        expect($filter->matchesPath('features/Greeting.feature'))->toBeTrue();
        expect($filter->matchesPath('features/other.feature'))->toBeFalse();
    });
});
