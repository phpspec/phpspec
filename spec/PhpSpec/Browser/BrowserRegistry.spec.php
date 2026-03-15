<?php

describe(BrowserRegistry::class, function () {

    it("saveState captures current state", function () {
        $saved = BrowserRegistry::saveState();
        expect($saved)->toHaveKey('client');
        expect($saved['client'])->toBeNull();
    });

    it("restoreState restores a previously saved state", function () {
        $saved = BrowserRegistry::saveState();
        BrowserRegistry::restoreState($saved);
        $again = BrowserRegistry::saveState();
        expect($again['client'])->toBe($saved['client']);
    });

    it("reset sets client to null", function () {
        BrowserRegistry::reset();
        $state = BrowserRegistry::saveState();
        expect($state['client'])->toBeNull();
    });

});
