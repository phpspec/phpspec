<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 * (c) Ciaran McNulty <ciaran@ciaranmcnulty.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Ai;

use InvalidArgumentException;
use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\Papi\PapiProvider;
use RuntimeException;

/** @internal */

final class ProviderFactory
{
    private const DEFAULTS = [
        'google' => ['class' => 'PapiAI\Google\GoogleProvider', 'model' => 'gemini-3.1-pro-preview'],
        'anthropic' => ['class' => 'PapiAI\Anthropic\AnthropicProvider', 'model' => 'claude-sonnet-5'],
        'openai' => ['class' => 'PapiAI\OpenAI\OpenAIProvider', 'model' => 'gpt-5.1'],
        'grok' => ['class' => 'PapiAI\Grok\GrokProvider', 'model' => 'grok-4'],
        'deepseek' => ['class' => 'PapiAI\DeepSeek\DeepSeekProvider', 'model' => 'deepseek-chat'],
        'ollama' => ['class' => 'PapiAI\Ollama\OllamaProvider', 'model' => 'llama3.1'],
    ];

    /**
     * Creates an AI provider instance from the given configuration.
     *
     * @param array{provider?: string, api_key: string, model?: string, base_url?: string} $aiConfig
     *
     * @return ProviderInterface
     *
     * @throws RuntimeException when PapiAI is not installed
     * @throws InvalidArgumentException when the provider name is unknown
     */
    public static function create(array $aiConfig): ProviderInterface
    {
        if (!interface_exists(\PapiAI\Core\Contracts\ProviderInterface::class)) {
            throw new RuntimeException(
                "AI features require PapiAI. Install it with the provider of your choice:\n"
                . "  composer require papi-ai/papi-core papi-ai/google       # for Gemini\n"
                . "  composer require papi-ai/papi-core papi-ai/anthropic     # for Claude\n"
                . "  composer require papi-ai/papi-core papi-ai/openai        # for GPT\n",
            );
        }

        $provider = $aiConfig['provider'] ?? 'google';

        if (!isset(self::DEFAULTS[$provider])) {
            throw new InvalidArgumentException("Unknown AI provider: $provider");
        }

        $class = self::DEFAULTS[$provider]['class'];

        if (!class_exists($class)) {
            throw new RuntimeException(
                "AI provider class '$class' not found. Install the package:\n"
                . '  composer require papi-ai/' . $provider,
            );
        }

        $papiProvider = $provider === 'ollama'
            ? new $class($aiConfig['base_url'] ?? 'http://localhost:11434')
            : new $class($aiConfig['api_key']);

        return new PapiProvider($papiProvider);
    }

    /**
     * Returns the default model identifier for a given AI provider.
     *
     * @param string $provider provider name (e.g. 'google', 'anthropic', 'openai')
     *
     * @return string
     */
    public static function defaultModel(string $provider): string
    {
        return self::DEFAULTS[$provider]['model']
            ?? throw new InvalidArgumentException("Unknown AI provider: $provider");
    }
}
