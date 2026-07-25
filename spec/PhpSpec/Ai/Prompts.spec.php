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

    it('ships a next prompt that teaches the outside-in, feature-first cycle in baby steps', function () use ($read) {
        $text = $read('next.txt');

        expect($text)->toContain('OUTSIDE-IN');
        expect($text)->toContain('FEATURE FIRST');
        expect($text)->toContain('OUTER CYCLE');
        expect($text)->toContain('INNER CYCLE');
        expect($text)->toContain('BARELY-ENOUGH-DESIGN');
        expect($text)->toContain('BABY STEP');
        expect($text)->toContain('NO FEATURES');
        expect($text)->toContain('Voice:');
    });

    it('ships the refactor command as a manifest with its rules as editable prose', function () use ($read) {
        $text = $read('commands/refactor.txt');

        expect($text)->toContain('temperature: 0.3');
        expect($text)->toContain('ONE baby-step refactoring');
        expect($text)->toContain('apply_refactoring');
    });

    it('ships the pair base guidance as an editable file with layout placeholders', function () use ($read) {
        $text = $read('instructions/pair-guidance.txt');

        expect($text)->toContain('%spec_path%');
        expect($text)->toContain('%features_path%');
        expect($text)->toContain('ObjectBehavior');           // the DSL guard rides along
        expect($text)->toContain('describe(');                 // the positive exemplar too
        expect($text)->toContain('given(), when(), then() are GLOBAL functions');
    });

    it('keeps next.txt pure coaching, with no machine-readable contract to leak', function () use ($read) {
        // The {type,target,reason} contract lives in the suggest_next tool
        // schema now; prose prompts carry nothing a conversation could leak.
        $text = $read('next.txt');

        expect($text)->not()->toContain('"type"');
        expect($text)->not()->toContain('machine-readable');
    });

});
