<?php

use PhpSpec\StoryBDD\TagExpression;

describe(TagExpression::class, function () {

    it('matches a single tag, written with or without its @', function () {
        expect((new TagExpression('@smoke'))->matches(['smoke', 'slow']))->toBeTrue();
        expect((new TagExpression('smoke'))->matches(['smoke']))->toBeTrue();
        expect((new TagExpression('@smoke'))->matches(['slow']))->toBeFalse();
        expect((new TagExpression('@smoke'))->matches([]))->toBeFalse();
    });

    it('reads and, or and not the Cucumber way, not binding tightest and and before or', function () {
        $expression = new TagExpression('@smoke and not @wip');

        expect($expression->matches(['smoke']))->toBeTrue();
        expect($expression->matches(['smoke', 'wip']))->toBeFalse();
        expect($expression->matches(['wip']))->toBeFalse();

        $either = new TagExpression('@a or @b and @c');
        expect($either->matches(['a']))->toBeTrue();
        expect($either->matches(['b']))->toBeFalse();
        expect($either->matches(['b', 'c']))->toBeTrue();

        expect((new TagExpression('not @wip'))->matches(['smoke']))->toBeTrue();
        expect((new TagExpression('not not @wip'))->matches(['wip']))->toBeTrue();
    });

    it('groups with parentheses', function () {
        $expression = new TagExpression('(@a or @b) and not @c');

        expect($expression->matches(['a']))->toBeTrue();
        expect($expression->matches(['b', 'c']))->toBeFalse();
        expect($expression->matches(['c']))->toBeFalse();
    });

    it('refuses an expression it cannot read, naming what it found', function () {
        foreach (['', '@a and', 'and @a', '(@a', '@a)', '@a @b', '@a xor @b'] as $bad) {
            try {
                new TagExpression($bad);
                expect("accepted '$bad'")->toBe('a refusal');
            } catch (\InvalidArgumentException $e) {
                expect($e->getMessage())->toContain('Tag expression');
            }
        }

        try {
            new TagExpression('@a and');
        } catch (\InvalidArgumentException $e) {
            expect($e->getMessage())->toBe('Tag expression "@a and" ends where a tag was expected.');
        }
    });

    it('says what it was given', function () {
        expect((string) new TagExpression(' @smoke and not @wip '))->toBe('@smoke and not @wip');
    });
});
