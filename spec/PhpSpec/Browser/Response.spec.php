<?php

use PhpSpec\Browser\Response;

describe(Response::class, function () {

    it('constructs with status, body, and headers', function () {
        $response = new Response(200, 'hello', ['Content-Type' => 'text/plain']);

        expect($response->status)->toBe(200);
        expect($response->body)->toBe('hello');
        expect($response->headers)->toBe(['Content-Type' => 'text/plain']);
    });

    it('decodes JSON body into json property', function () {
        $body = json_encode(['name' => 'Chuck', 'age' => 30]);
        $response = new Response(200, $body);

        expect($response->json)->toBe(['name' => 'Chuck', 'age' => 30]);
    });

    it('returns empty array for non-JSON body', function () {
        $response = new Response(200, 'plain text');

        expect($response->json)->toBe([]);
    });

    it('returns empty array for empty body', function () {
        $response = new Response(204, '');

        expect($response->json)->toBe([]);
    });

    it('defaults headers to empty array', function () {
        $response = new Response(200, '');

        expect($response->headers)->toBe([]);
    });

    it('handles nested JSON', function () {
        $body = json_encode(['data' => ['user' => ['name' => 'Chuck']]]);
        $response = new Response(200, $body);

        expect($response->json['data']['user']['name'])->toBe('Chuck');
    });

    it('throws for undefined property access', function () {
        $response = new Response(200, '');

        expect(fn() => $response->foo)->toThrow(\InvalidArgumentException::class);
    });

    it('reports json as isset', function () {
        $response = new Response(200, '{}');

        expect(isset($response->json))->toBeTrue();
        expect(isset($response->foo))->toBeFalse();
    });

});
