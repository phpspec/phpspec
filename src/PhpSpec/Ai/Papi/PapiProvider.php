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

namespace PhpSpec\Ai\Papi;

use PapiAI\Core\Contracts\ProviderInterface as PapiProviderInterface;
use PapiAI\Core\Message as PapiMessage;
use PapiAI\Core\ToolCall as PapiToolCall;
use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\Message;
use PhpSpec\Ai\Response;
use PhpSpec\Ai\Role;
use PhpSpec\Ai\ToolCall;

/**
 * Adapter that bridges PhpSpec's AI provider interface to PapiAI.
 *
 * Converts PhpSpec Message/Response/ToolCall objects to and from their
 * PapiAI equivalents so PhpSpec code never references PapiAI directly.
 */
final class PapiProvider implements ProviderInterface
{
    public function __construct(
        private readonly PapiProviderInterface $provider,
    ) {}

    /**
     * @inheritDoc
     */
    public function chat(array $messages, array $options = []): Response
    {
        $papiMessages = array_map(self::toPapiMessage(...), $messages);

        $papiResponse = $this->provider->chat($papiMessages, $options);

        return self::fromPapiResponse($papiResponse);
    }

    private static function toPapiMessage(Message $message): PapiMessage
    {
        return match ($message->role) {
            Role::System => PapiMessage::system($message->content),
            Role::User => PapiMessage::user($message->content),
            Role::Assistant => PapiMessage::assistant(
                $message->content,
                $message->toolCalls !== null
                    ? array_map(self::toPapiToolCall(...), $message->toolCalls)
                    : null,
            ),
            Role::Tool => PapiMessage::toolResult(
                $message->toolCallId ?? '',
                $message->content,
            ),
        };
    }

    private static function toPapiToolCall(ToolCall $toolCall): PapiToolCall
    {
        return new PapiToolCall($toolCall->id, $toolCall->name, $toolCall->arguments);
    }

    private static function fromPapiResponse(\PapiAI\Core\Response $response): Response
    {
        $toolCalls = array_map(
            fn(PapiToolCall $tc) => new ToolCall($tc->id, $tc->name, $tc->arguments),
            $response->toolCalls,
        );

        return new Response($response->text, $toolCalls);
    }
}
