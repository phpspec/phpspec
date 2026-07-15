<?php

use PhpSpec\Report\Formatter\Agent\ValueExporter;

describe(ValueExporter::class, function () {

    it('passes scalars and null through unchanged', function () {
        expect(ValueExporter::export(42))->toBe(42);
        expect(ValueExporter::export(3.5))->toBe(3.5);
        expect(ValueExporter::export(true))->toBe(true);
        expect(ValueExporter::export(null))->toBe(null);
    });

    it('passes a short string through unchanged', function () {
        expect(ValueExporter::export('hello'))->toBe('hello');
    });

    it('truncates a string longer than 200 characters and says so', function () {
        $out = ValueExporter::export(str_repeat('x', 250));

        expect($out['truncated'])->toBe(true);
        expect($out['length'])->toBe(250);
        expect(strlen($out['value']))->toBe(200);
    });

    it('renders an object as ClassName#id without dumping its graph', function () {
        expect(ValueExporter::export(new \RuntimeException('boom')))->toStartWith('RuntimeException#');
    });

    it('exports a small array recursively, hashing nested objects', function () {
        $out = ValueExporter::export([1, 'two', new \stdClass()]);

        expect($out[0])->toBe(1);
        expect($out[1])->toBe('two');
        expect($out[2])->toStartWith('stdClass#');
    });

    it('truncates an array with more than 10 elements and says so', function () {
        $out = ValueExporter::export(range(1, 25));

        expect($out['truncated'])->toBe(true);
        expect($out['length'])->toBe(25);
        expect(count($out['value']))->toBe(10);
        expect($out['value'][0])->toBe(1);
    });

    it('preserves associative array keys', function () {
        expect(ValueExporter::export(['a' => 1, 'b' => 2]))->toBe(['a' => 1, 'b' => 2]);
    });

    it('bounds recursion on a deeply nested array', function () {
        $deep = 'too deep';
        for ($i = 0; $i < 6; $i++) {
            $deep = [$deep];
        }

        $node = ValueExporter::export($deep);
        for ($i = 0; $i < 5; $i++) {
            $node = $node[0];
        }

        expect($node['truncated'])->toBe(true);
    });

});
