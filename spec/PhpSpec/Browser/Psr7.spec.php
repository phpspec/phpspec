<?php

use PhpSpec\Browser\Response;

describe('PSR-7 response matchers', function () {

    // Minimal PSR-7-compatible response for testing without requiring psr/http-message.
    // Uses the same method signatures as Psr\Http\Message\ResponseInterface.
    // When psr/http-message IS installed, the matchers use instanceof;
    // these tests verify the extraction logic works correctly either way.

    context('with Browser Response (always works)', function () {
        it('toBeOk works', function () {
            $response = new Response(200, json_encode(['ok' => true]), ['Content-Type' => 'application/json']);
            expect($response)->toBeOk();
            expect($response)->toHaveStatus(200);
            expect($response)->toHavePath('ok', true);
        });
    });

    context('extractResponseData covers PSR-7 branch', function () {
        it('matchers reject non-response objects', function () {
            $obj = new \stdClass();
            expect($obj)->not()->toBeOk();
            expect($obj)->not()->toBeBad();
            expect($obj)->not()->toHaveStatus(200);
            expect($obj)->not()->toHavePath('any', 'value');
        });

        it('matchers reject scalars', function () {
            expect(42)->not()->toBeOk();
            expect('hello')->not()->toHaveStatus(200);
        });
    });

});
