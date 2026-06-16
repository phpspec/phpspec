<?php

use PhpSpec\Browser\Response;

describe('Response matchers', function () {

    context('toBeOk()', function () {
        it('passes for status 200', function () {
            $response = new Response(200, '');
            expect($response)->toBeOk();
        });

        it('fails for status 404', function () {
            $response = new Response(404, '');
            expect($response)->not()->toBeOk();
        });

        it('supports negation', function () {
            $response = new Response(500, '');
            expect($response)->not()->toBeOk();
        });
    });

    context('toBeBad()', function () {
        it('passes for status 400', function () {
            $response = new Response(400, '');
            expect($response)->toBeBad();
        });

        it('fails for status 200', function () {
            $response = new Response(200, '');
            expect($response)->not()->toBeBad();
        });

        it('supports negation', function () {
            $response = new Response(201, '');
            expect($response)->not()->toBeBad();
        });
    });

    context('toHaveStatus()', function () {
        it('passes for matching status', function () {
            $response = new Response(201, '');
            expect($response)->toHaveStatus(201);
        });

        it('fails for non-matching status', function () {
            $response = new Response(200, '');
            expect($response)->not()->toHaveStatus(404);
        });

        it('works with 204 No Content', function () {
            $response = new Response(204, '');
            expect($response)->toHaveStatus(204);
        });

        it('works with 500 Server Error', function () {
            $response = new Response(500, '');
            expect($response)->toHaveStatus(500);
        });

        it('supports negation', function () {
            $response = new Response(200, '');
            expect($response)->not()->toHaveStatus(201);
        });
    });

    context('toHavePath()', function () {
        it('passes when path matches at top level', function () {
            $response = new Response(200, json_encode(['name' => 'Chuck']));
            expect($response)->toHavePath('name', 'Chuck');
        });

        it('passes for nested dot-notation path', function () {
            $response = new Response(200, json_encode([
                'data' => ['user' => ['name' => 'Chuck']],
            ]));
            expect($response)->toHavePath('data.user.name', 'Chuck');
        });

        it('fails when path does not exist', function () {
            $response = new Response(200, json_encode(['name' => 'Chuck']));
            expect($response)->not()->toHavePath('missing.path', 'Chuck');
        });

        it('fails when value does not match', function () {
            $response = new Response(200, json_encode(['name' => 'Chuck']));
            expect($response)->not()->toHavePath('name', 'Bruce');
        });

        it('works with integer values', function () {
            $response = new Response(200, json_encode(['meta' => ['total' => 42]]));
            expect($response)->toHavePath('meta.total', 42);
        });

        it('supports negation', function () {
            $response = new Response(200, json_encode(['name' => 'Chuck']));
            expect($response)->not()->toHavePath('name', 'NotChuck');
        });
    });

    context('toHaveHeader()', function () {
        it('passes when header exists', function () {
            $response = new Response(200, '', ['Content-Type' => 'application/json']);
            expect($response)->toHaveHeader('Content-Type');
        });

        it('passes when header matches value', function () {
            $response = new Response(200, '', ['Content-Type' => 'application/json']);
            expect($response)->toHaveHeader('Content-Type', 'application/json');
        });

        it('fails when header is missing', function () {
            $response = new Response(200, '', []);
            expect($response)->not()->toHaveHeader('X-Custom');
        });

        it('fails when header value does not match', function () {
            $response = new Response(200, '', ['Content-Type' => 'text/html']);
            expect($response)->not()->toHaveHeader('Content-Type', 'application/json');
        });

        it('supports negation for existence', function () {
            $response = new Response(200, '', ['Content-Type' => 'text/html']);
            expect($response)->not()->toHaveHeader('X-Missing');
        });

        it('supports negation for value', function () {
            $response = new Response(200, '', ['Content-Type' => 'text/html']);
            expect($response)->not()->toHaveHeader('Content-Type', 'application/xml');
        });
    });

    context('toRedirectTo()', function () {
        it('passes for 301 with matching Location', function () {
            $response = new Response(301, '', ['Location' => '/new-page']);
            expect($response)->toRedirectTo('/new-page');
        });

        it('passes for 302 with matching Location', function () {
            $response = new Response(302, '', ['Location' => '/login']);
            expect($response)->toRedirectTo('/login');
        });

        it('passes for 307 temporary redirect', function () {
            $response = new Response(307, '', ['Location' => '/temp']);
            expect($response)->toRedirectTo('/temp');
        });

        it('fails for non-redirect status', function () {
            $response = new Response(200, '', ['Location' => '/page']);
            expect($response)->not()->toRedirectTo('/page');
        });

        it('fails when Location does not match', function () {
            $response = new Response(302, '', ['Location' => '/other']);
            expect($response)->not()->toRedirectTo('/login');
        });

        it('fails when no Location header', function () {
            $response = new Response(302, '', []);
            expect($response)->not()->toRedirectTo('/anywhere');
        });

        it('supports negation', function () {
            $response = new Response(301, '', ['Location' => '/actual']);
            expect($response)->not()->toRedirectTo('/expected');
        });
    });

});
