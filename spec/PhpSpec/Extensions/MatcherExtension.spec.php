<?php

use PhpSpec\Extensions\MatcherExtension;

describe(MatcherExtension::class, function () {

    let('matcher', fn() => new class extends MatcherExtension {
        public function getName(): string
        {
            return 'toBePositive';
        }

        public function match(mixed $actual, mixed ...$args): bool
        {
            return $actual > 0;
        }

        public function failureMessage(mixed $actual): string
        {
            return "Expected $actual to be positive";
        }
    });

    it('returns the matcher name', function () {
        expect($this->matcher->getName())->toBe('toBePositive');
    });

    it('matches positive values', function () {
        expect($this->matcher->match(5))->toBeTrue();
    });

    it('rejects non-positive values', function () {
        expect($this->matcher->match(-1))->toBeFalse();
    });

    it('returns a failure message', function () {
        expect($this->matcher->failureMessage(-1))->toBe('Expected -1 to be positive');
    });

    it('returns a default negated failure message', function () {
        expect($this->matcher->negatedFailureMessage(5))->toBe('Expected not: Expected 5 to be positive');
    });
});
