<?php

use PhpSpec\Ai\PromptLibrary;

// The shipped prompt artifacts, asserted through the library (with @include
// expansion), because the expanded text is what a model actually receives. Raw
// reads are used only to lock the single-sourcing directives themselves.
describe('prompt artifacts', function () {

    $read = fn(string $name): string => (new PromptLibrary())->read($name);
    $raw = fn(string $name): string => (string) @file_get_contents(getcwd() . '/src/PhpSpec/Ai/Prompts/' . $name . '.txt');

    it('ships a navigator prompt that forbids writing, teaches the abstraction ladder, and guards the design', function () use ($read) {
        $text = $read('navigator');

        expect($text)->toContain('NAVIGATOR');
        expect($text)->toContain('never write');
        expect($text)->toContain('Intent');
        expect($text)->toContain('park');
        expect($text)->toContain('Guard the design');
    });

    it('ships a driver prompt that runs the collaboration cycle and caps one artifact per turn', function () use ($read) {
        $text = $read('driver');

        expect($text)->toContain('DRIVER');
        expect($text)->toContain('artifact per turn');
        expect($text)->toContain('CLARIFY');
        expect($text)->toContain('CONFIRM');
        expect($text)->toContain('add_example');
    });

    it('steers open clarifying questions to plain text, reserving ask_user for yes/no', function () use ($read) {
        $text = $read('driver');

        expect($text)->toContain('plain-text question');
        expect($text)->toContain('Use ask_user only for a straight yes/no.');
    });

    it('ships the syntax primers as single-source files', function () use ($read) {
        expect($read('instructions/spec-syntax'))->toContain('describe(');
        expect($read('instructions/spec-syntax'))->toContain('ObjectBehavior');           // the guard rides with the exemplar
        expect($read('instructions/steps-syntax'))->toContain('given(), when(), then() are GLOBAL functions');
        expect($read('instructions/gherkin-syntax'))->toContain('Feature:');
    });

    it('teaches generate every syntax it can write, via the primers', function () use ($read, $raw) {
        $text = $read('commands/generate');

        expect($text)->toContain('describe(');                       // spec DSL exemplar reached the prompt
        expect($text)->toContain('given(');                          // steps exemplar too
        expect($text)->toContain('Feature:');                        // and Gherkin
        expect($raw('commands/generate'))->toContain('@include instructions/spec-syntax');   // single-sourced, not copied
    });

    it('keeps every syntax primer out of the advisor: next suggests, it does not write', function () use ($read) {
        $text = $read('commands/next');

        expect($text)->not()->toContain('describe(');
        expect($text)->not()->toContain('given(');
    });

    it('ships every pair write tool description as an editable file', function () use ($read) {
        expect($read('tools/describe'))->toContain('describe() skeleton');
        expect($read('tools/add_example'))->toContain('ONE it() example');
        expect($read('tools/generate_feature'))->toContain('Gherkin');
        expect($read('tools/generate_steps'))->toContain('.steps.php');
        expect($read('tools/write_file'))->toContain('not covered by describe');
        expect($read('tools/update_file'))->toContain('add_example');
        expect($read('tools/offer_change'))->toContain('diff');
        expect($read('tools/suggest_next'))->toContain('suggestion');
    });

    it('teaches the navigator to offer concrete changes instead of dictating typing', function () use ($read) {
        $text = $read('navigator');

        expect($text)->toContain('offer_change');
        expect($text)->toContain('never write');   // unbidden writes stay forbidden
    });

    it('ships the refactor command as a manifest with its rules as editable prose', function () use ($read) {
        $text = $read('commands/refactor');

        expect($text)->toContain('temperature: 0.3');
        expect($text)->toContain('ONE baby-step refactoring');
        expect($text)->toContain('apply_refactoring');
    });

    it('ships the pair base guidance with layout placeholders, composed from the primers', function () use ($read, $raw) {
        $text = $read('instructions/pair-guidance');

        expect($text)->toContain('%spec_path%');
        expect($text)->toContain('%features_path%');
        expect($text)->toContain('ObjectBehavior');
        expect($text)->toContain('describe(');
        expect($text)->toContain('given(), when(), then() are GLOBAL functions');
        expect($raw('instructions/pair-guidance'))->toContain('@include instructions/spec-syntax');
    });

    it('ships a next prompt that teaches the outside-in, feature-first cycle in baby steps', function () use ($read, $raw) {
        $text = $read('next');

        expect($text)->toContain('OUTSIDE-IN');
        expect($text)->toContain('FEATURE FIRST');
        expect($text)->toContain('OUTER CYCLE');
        expect($text)->toContain('INNER CYCLE');
        expect($text)->toContain('BARELY-ENOUGH-DESIGN');
        expect($text)->toContain('BABY STEP');
        expect($text)->toContain('NO FEATURES');
        expect($text)->toContain('Voice:');
        expect($raw('next'))->toContain('@include instructions/tdd-cycle');   // the cycle is single-sourced
    });

    it('keeps next.txt pure coaching, with no machine-readable contract to leak', function () use ($read) {
        // The {type,target,reason} contract lives in the suggest_next tool
        // schema now; prose prompts carry nothing a conversation could leak.
        $text = $read('next');

        expect($text)->not()->toContain('"type"');
        expect($text)->not()->toContain('machine-readable');
    });

});
