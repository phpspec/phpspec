<?php

use PapiAI\Core\Exception\ConfigurationException;
use PhpSpec\Ai\Papi\PapiCatalogue;

// The adapter over papi's registry, run against the real one: development
// installs no papi provider package, so the registry papi discovers is empty.
describe(PapiCatalogue::class, function () {

    it('finds no provider where no papi provider package is installed', function () {
        expect((new PapiCatalogue())->installed())->toBe([]);
    });

    it('knows no settings of a provider no package provides', function () {
        expect((new PapiCatalogue())->settings('anthropic'))->toBe([]);
    });

    it('refuses a provider no package provides, in papi\'s words', function () {
        expect(fn() => (new PapiCatalogue())->create('google', ['api_key' => 'k']))
            ->toThrow(new ConfigurationException('Provider "google" is not installed, and no papi provider package is.'));
    });
});
