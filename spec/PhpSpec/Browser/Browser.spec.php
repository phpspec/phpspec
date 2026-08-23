<?php

use PhpSpec\Browser\Browser;
use PhpSpec\Browser\Client;

describe(Browser::class, function () {

    it('is what the bundled client implements', function () {
        expect(new Client('http://localhost'))->toBeAnInstanceOf(Browser::class);
    });

});
