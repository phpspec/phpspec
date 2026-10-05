<?php

use PhpSpec\Report\Formatter\Agent\Json;

describe(Json::class, function () {

    it('writes one event as one line, slashes and unicode as they are, zero fractions kept', function () {
        expect(Json::line(['v' => 2, 'path' => 'spec/App.spec.php', 'ratio' => 1.0, 'name' => 'café']))
            ->toBe('{"v":2,"path":"spec/App.spec.php","ratio":1.0,"name":"café"}' . "\n");
    });

    it('substitutes bytes that are not UTF-8 instead of dropping the event', function () {
        expect(Json::line(['output' => "bad\xFF"]))->toBe('{"output":"bad' . "\u{FFFD}" . '"}' . "\n");
    });

    it('keeps an event whole and says what it could not encode, never writing an empty object', function () {
        $stream = fopen('php://memory', 'r');

        $line = json_decode(Json::line(['v' => 2, 'event' => 'example', 'id' => 'abc', 'odd' => $stream]), true);

        expect($line['v'])->toBe(2);
        expect($line['event'])->toBe('example');
        expect($line['id'])->toBe('abc');
        expect($line['encoding'])->toContain('Type is not supported');

        fclose($stream);
    });
});
