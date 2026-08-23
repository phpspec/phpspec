<?php

use PhpSpec\Browser\Browser;
use PhpSpec\Browser\BrowserRegistry;
use PhpSpec\Browser\Response;

describe(BrowserRegistry::class, function () {

    beforeAll(function () {
        BrowserRegistry::reset();
    });

    afterEach(function () {
        BrowserRegistry::reset();
    });

    beforeEach(function () {
        $this->recorder = new class implements Browser {
            public array $requests = [];

            public function request(string $method, string $url, array $options = []): Response
            {
                $this->requests[] = [$method, $url];

                return new Response(200, 'ok', []);
            }
        };
    });

    it('resolves a relative path against the base URL it was initialised with', function () {
        BrowserRegistry::use($this->recorder);
        BrowserRegistry::init('http://localhost:8080/');

        BrowserRegistry::request('GET', '/health');

        expect($this->recorder->requests)->toBe([['GET', 'http://localhost:8080/health']]);
    });

    it('passes an absolute URL through untouched, initialised or not', function () {
        BrowserRegistry::use($this->recorder);

        BrowserRegistry::request('GET', 'https://example.com/status');

        expect($this->recorder->requests)->toBe([['GET', 'https://example.com/status']]);
    });

    it('refuses a relative path when it has no base URL', function () {
        BrowserRegistry::use($this->recorder);

        expect(fn() => BrowserRegistry::request('GET', '/health'))
            ->toThrow(RuntimeException::class, 'No base URL configured: call BrowserRegistry::init() with one.');
    });

    it('drives whatever browser it was given', function () {
        BrowserRegistry::use($this->recorder);
        BrowserRegistry::init('http://localhost');

        $response = BrowserRegistry::request('GET', '/');

        expect($response->status)->toBe(200);
    });

    it('round-trips its state for nested-run isolation', function () {
        BrowserRegistry::use($this->recorder);
        BrowserRegistry::init('http://localhost');
        $saved = BrowserRegistry::saveState();

        BrowserRegistry::reset();
        BrowserRegistry::restoreState($saved);
        BrowserRegistry::request('GET', '/after');

        expect($this->recorder->requests)->toBe([['GET', 'http://localhost/after']]);
    });

});
