<?php

use PhpSpec\Browser\Client;
use PhpSpec\Browser\Response;

describe(Client::class, function () {

    it('instantiates with a base URL', function () {
        $client = new Client('http://localhost:8080');

        expect($client)->toBeAnInstanceOf(Client::class);
    });

    it('makes a GET request and parses the response', function () {
        $client = new Client('http://fake-host', function (string $url, $context) {
            return [
                'body' => '{"message":"hello"}',
                'headers' => ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
            ];
        });

        $response = $client->request('GET', '/test');

        expect($response)->toBeAnInstanceOf(Response::class);
        expect($response->status)->toBe(200);
        expect($response->body)->toBe('{"message":"hello"}');
        expect($response->headers)->toHaveKey('Content-Type');
    });

    it('sends JSON body on POST', function () {
        $capturedContext = null;

        $client = new Client('http://fake-host', function (string $url, $context) use (&$capturedContext) {
            $capturedContext = stream_context_get_options($context);

            return [
                'body' => '{"created":true}',
                'headers' => ['HTTP/1.1 201 Created'],
            ];
        });

        $response = $client->request('POST', '/items', ['json' => ['name' => 'test']]);

        expect($response->status)->toBe(201);
        expect($response->body)->toBe('{"created":true}');
        expect($capturedContext['http']['method'])->toBe('POST');
        expect($capturedContext['http']['content'])->toBe('{"name":"test"}');
    });

    it('sends raw body on POST', function () {
        $capturedContext = null;

        $client = new Client('http://fake-host', function (string $url, $context) use (&$capturedContext) {
            $capturedContext = stream_context_get_options($context);

            return ['body' => 'ok', 'headers' => ['HTTP/1.1 200 OK']];
        });

        $response = $client->request('POST', '/upload', ['body' => 'raw content']);

        expect($capturedContext['http']['content'])->toBe('raw content');
    });

    it('handles connection failure gracefully', function () {
        $client = new Client('http://fake-host', function () {
            return ['body' => false, 'headers' => []];
        });

        $response = $client->request('GET', '/fail');

        expect($response->status)->toBe(0);
        expect($response->body)->toBe('');
    });

    it('builds correct URL from base and path', function () {
        $capturedUrl = null;

        $client = new Client('http://localhost:8080/', function (string $url) use (&$capturedUrl) {
            $capturedUrl = $url;

            return ['body' => '', 'headers' => ['HTTP/1.1 200 OK']];
        });

        $client->request('GET', '/api/users');

        expect($capturedUrl)->toBe('http://localhost:8080/api/users');
    });

    it('adds custom headers to request', function () {
        $capturedContext = null;

        $client = new Client('http://fake-host', function (string $url, $context) use (&$capturedContext) {
            $capturedContext = stream_context_get_options($context);

            return ['body' => '', 'headers' => ['HTTP/1.1 200 OK']];
        });

        $client->request('GET', '/api', ['headers' => ['Authorization' => 'Bearer token']]);

        expect($capturedContext['http']['header'])->toContain('Authorization: Bearer token');
    });

});
