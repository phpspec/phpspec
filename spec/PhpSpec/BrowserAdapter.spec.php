<?php

use PhpSpec\Browser\Browser;
use PhpSpec\Browser\Response;
use PhpSpec\BrowserAdapter;

describe(BrowserAdapter::class, function () {

    beforeEach(function () {
        $this->inner = new class implements Browser {
            public array $requests = [];

            public function request(string $method, string $url, array $options = []): Response
            {
                $this->requests[] = [$method, $url, $options];

                return new Response(500, 'the widget service is down', ['X-Server' => 'test']);
            }
        };
    });

    it('forwards the request and returns what the browser answered', function () {
        $adapter = new BrowserAdapter($this->inner);

        $response = $adapter->request('GET', 'http://host/widgets');

        expect($this->inner->requests)->toBe([['GET', 'http://host/widgets', []]]);
        expect($response->status)->toBe(500);
    });

    it('reports each exchange through its default callback', function () {
        $seen = [];
        $adapter = new BrowserAdapter($this->inner, function (...$exchange) use (&$seen) {
            $seen[] = $exchange;
        });

        $adapter->request('GET', 'http://host/widgets');

        expect($seen)->toBe([
            ['GET', 'http://host/widgets', 500, 'the widget service is down', ['X-Server' => 'test']],
        ]);
    });

    it('lets a per-request callback take over from the default', function () {
        $called = [];
        $adapter = new BrowserAdapter($this->inner, function () use (&$called) {
            $called[] = 'default';
        });

        $adapter->request('GET', 'http://host/widgets', [
            'callback' => function () use (&$called) {
                $called[] = 'mine';
            },
        ]);

        expect($called)->toBe(['mine']);
    });

    it('needs no callback at all', function () {
        $response = (new BrowserAdapter($this->inner))->request('DELETE', 'http://host/widgets/1');

        expect($response->body)->toBe('the widget service is down');
    });

});
