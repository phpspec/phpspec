<?php

use PhpSpec\Report\HtmlTheme;

describe(HtmlTheme::class, function () {

    it('wraps content in a full HTML document with the brand stylesheet', function () {
        $page = HtmlTheme::page('PhpSpec Results', '<p>content</p>');

        expect($page)->toContain('<!DOCTYPE html>');
        expect($page)->toContain('<title>PhpSpec Results</title>');
        expect($page)->toContain('<p>content</p>');
        expect($page)->toContain('--ps-red');
        expect($page)->toContain('--ps-green');
    });

    it('renders the header band with the logo, wordmark, a subtitle and meta text', function () {
        $header = HtmlTheme::header('Results', '4 examples · 2 failures');

        expect($header)->toContain('phpspec');
        expect($header)->toContain('Results');
        expect($header)->toContain('4 examples · 2 failures');
        expect($header)->toContain('class="band"');
        expect($header)->toContain('data:image/png;base64,');
        expect($header)->toContain('alt="phpspec"');
    });

    it('renders the pass-ratio meter with a clamped width', function () {
        expect(HtmlTheme::meter(50.0))->toContain('width:50.0%');
        expect(HtmlTheme::meter(150.0))->toContain('width:100.0%');
        expect(HtmlTheme::meter(-10.0))->toContain('width:0.0%');
    });
});
