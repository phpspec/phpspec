<?php

use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\Papi\PapiProvider;
use PhpSpec\Ai\ProviderFactory;

describe(ProviderFactory::class, function () {

    it('creates a PapiProvider for provider google', function () {
        $provider = ProviderFactory::create([
            'provider' => 'google',
            'api_key' => 'test-key',
        ]);

        expect($provider)->toBeAnInstanceOf(PapiProvider::class);
    });

    it('returns a ProviderInterface', function () {
        $provider = ProviderFactory::create([
            'provider' => 'google',
            'api_key' => 'test-key',
        ]);

        expect($provider)->toBeAnInstanceOf(ProviderInterface::class);
    });

    it('defaults to google when provider is not specified', function () {
        $provider = ProviderFactory::create([
            'api_key' => 'test-key',
        ]);

        expect($provider)->toBeAnInstanceOf(PapiProvider::class);
    });

    it('throws on unknown provider name', function () {
        expect(fn() => ProviderFactory::create([
            'provider' => 'unknown',
            'api_key' => 'test-key',
        ]))->toThrow(\InvalidArgumentException::class, 'Unknown AI provider: unknown');
    });

    it('throws RuntimeException when provider package is not installed', function () {
        expect(fn() => ProviderFactory::create([
            'provider' => 'anthropic',
            'api_key' => 'test-key',
        ]))->toThrow(\RuntimeException::class, "AI provider class 'PapiAI\Anthropic\AnthropicProvider' not found. Install the package:\n  composer require papi-ai/anthropic");
    });

    it('returns the default model for google', function () {
        expect(ProviderFactory::defaultModel('google'))->toBe('gemini-3.1-pro-preview');
    });

    it('returns the default model for anthropic', function () {
        expect(ProviderFactory::defaultModel('anthropic'))->toBe('claude-sonnet-5');
    });

    it('returns the default model for openai', function () {
        expect(ProviderFactory::defaultModel('openai'))->toBe('gpt-5.1');
    });

    it('throws on unknown provider in defaultModel', function () {
        expect(fn() => ProviderFactory::defaultModel('unknown'))
            ->toThrow(\InvalidArgumentException::class, 'Unknown AI provider: unknown');
    });
});
