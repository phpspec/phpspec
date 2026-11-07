<?php

use PhpSpec\Api\Surface;

describe(Surface::class, function () {

    it('lists every mock function, what follows allow(), the counts a call expectation takes, and what a mock expectation adds to the matchers', function () {
        $mocks = (new Surface())->toArray()['mocks'];
        $names = fn(string $key): array => array_column($mocks[$key], 'name');

        expect($names('functions'))->toBe(['mock', 'dummy', 'allow']);
        expect($names('stubs'))->toBe(['toReturn', 'andReturn', 'toReturnUsing', 'toThrow', 'ignoreOthers']);
        expect($names('counts'))->toBe(['once', 'twice', 'exactly', 'atLeast', 'atMost', 'times']);
        expect($names('expectations'))->toBe(['toReturn', 'andReturn', 'toThrow', 'toBeCalled', 'toHaveBeenCalled']);
    });

    it('names a method that returns self by its class, as every PHP version then reads it alike', function () {
        $counts = (new Surface())->toArray()['mocks']['counts'];

        expect($counts[0]['signature'])->toBe('once(): PhpSpec\Mock\CallCountExpectation');
    });

    it('names the docs by files that exist where PhpSpec is installed', function () {
        $docs = (new Surface())->toArray()['docs'];

        expect($docs)->toHaveKey('matchers');
        expect($docs['matchers'])->toBe(realpath(__DIR__ . '/../../../docs/matchers.md'));
        foreach ($docs as $path) {
            expect(is_file($path))->toBeTrue();
        }
    });
});
