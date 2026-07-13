<?php

describe('pair role prompt artifacts', function () {

    $read = fn(string $name): string => (string) @file_get_contents(getcwd() . '/src/PhpSpec/Ai/Prompts/' . $name);

    it('ships a navigator prompt that forbids writing, teaches the abstraction ladder, and guards the design', function () use ($read) {
        $text = $read('navigator.txt');

        expect($text)->toContain('NAVIGATOR');
        expect($text)->toContain('never write');
        expect($text)->toContain('Intent');
        expect($text)->toContain('park');
        expect($text)->toContain('Guard the design');
    });

    it('ships a driver prompt that runs the collaboration cycle and caps one artifact per turn', function () use ($read) {
        $text = $read('driver.txt');

        expect($text)->toContain('DRIVER');
        expect($text)->toContain('artifact per turn');
        expect($text)->toContain('CLARIFY');
        expect($text)->toContain('CONFIRM');
        expect($text)->toContain('add_example');
    });

    it('steers open clarifying questions to plain text, reserving ask_user for yes/no', function () use ($read) {
        $text = $read('driver.txt');

        expect($text)->toContain('plain-text question');
        expect($text)->toContain('Use ask_user only for a straight yes/no.');
    });

});
