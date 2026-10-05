<?php

use PhpSpec\Api\Surface;

describe(Surface::class, function () {

    it('names the docs by files that exist where PhpSpec is installed', function () {
        $docs = (new Surface())->toArray()['docs'];

        expect($docs)->toHaveKey('matchers');
        expect($docs['matchers'])->toBe(realpath(__DIR__ . '/../../../docs/matchers.md'));
        foreach ($docs as $path) {
            expect(is_file($path))->toBeTrue();
        }
    });
});
