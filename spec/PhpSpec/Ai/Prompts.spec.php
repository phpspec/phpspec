<?php

describe('pair role prompt artifacts', function () {

    $read = fn(string $name): string => (string) @file_get_contents(getcwd() . '/src/PhpSpec/Ai/Prompts/' . $name);

    it('ships a navigator prompt that forbids writing and teaches the abstraction ladder', function () use ($read) {
        $text = $read('navigator.txt');

        expect($text)->toContain('NAVIGATOR');
        expect($text)->toContain('never write');
        expect($text)->toContain('Intent');
        expect($text)->toContain('park');
    });

    it('ships a driver prompt that enforces one artifact per turn', function () use ($read) {
        $text = $read('driver.txt');

        expect($text)->toContain('DRIVER');
        expect($text)->toContain('one artifact per turn');
    });

});
