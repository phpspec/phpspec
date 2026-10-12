<?php

use PhpSpec\Specification\Context;
use PhpSpec\Specification\Example;
use PhpSpec\Specification\Namesakes;

describe(Namesakes::class, function () {
    it('numbers a child whose title a sibling of its kind already has, the first keeping its own', function () {
        $namesakes = new Namesakes();
        $first = new Example('returns the total', fn() => null);
        $second = new Example('returns the total', fn() => null);
        $third = new Example('returns the total', fn() => null);

        foreach ([$first, $second, $third] as $example) {
            $namesakes->number($example);
        }

        expect([$first->getTitle(), $second->getTitle(), $third->getTitle()])
            ->toBe(['returns the total', 'returns the total #2', 'returns the total #3']);
    });

    it('leaves alone a child that shares a title only with a sibling of another kind', function () {
        $namesakes = new Namesakes();
        $context = new Context('totals', fn() => null);
        $example = new Example('totals', fn() => null);

        $namesakes->number($context);
        $namesakes->number($example);

        expect($example->getTitle())->toBe('totals');
    });
});
