<?php

use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\Response;
use PhpSpec\Ai\ToolCall;

/**
 * Spec-side fake provider shared by the agent specs and the eval corpus: replays
 * scripted responses (optionally built from a recording fixture) and records
 * every request it receives, so specs can assert on the exact messages and
 * options sent, or that the model was never consulted at all.
 */
final class ReplayProvider implements ProviderInterface
{
    /** @var list<array{messages: list<\PhpSpec\Ai\Message>, options: array<string, mixed>}> */
    public array $requests = [];

    private int $turn = 0;

    /**
     * @param list<Response> $responses one response per expected chat call; the last repeats
     */
    public function __construct(private readonly array $responses = []) {}

    /**
     * Builds a single-turn replay from an eval recording document: the recorded
     * text plus any recorded tool calls.
     *
     * @param array{response: array{text?: string, tool_calls?: list<array{id?: string, name: string, arguments?: array<string, mixed>}>}} $recording
     */
    public static function fromRecording(array $recording): self
    {
        $calls = array_map(
            static fn(array $call): ToolCall => new ToolCall($call['id'] ?? '1', $call['name'], $call['arguments'] ?? []),
            $recording['response']['tool_calls'] ?? [],
        );

        return new self([new Response($recording['response']['text'] ?? '', $calls)]);
    }

    /**
     * Builds a whole-conversation replay from a session recording: one scripted
     * response per provider call, in order, across every turn's rounds.
     *
     * @param array{turns: list<array{rounds?: list<array{response: array{text?: string, tool_calls?: list<array{id?: string, name: string, arguments?: array<string, mixed>}>}}>}>} $recording
     */
    public static function fromConversation(array $recording): self
    {
        $responses = [];
        foreach ($recording['turns'] ?? [] as $turn) {
            foreach ($turn['rounds'] ?? [] as $round) {
                $calls = array_map(
                    static fn(array $call): ToolCall => new ToolCall($call['id'] ?? '1', $call['name'], $call['arguments'] ?? []),
                    $round['response']['tool_calls'] ?? [],
                );
                $responses[] = new Response($round['response']['text'] ?? '', $calls);
            }
        }

        return new self($responses);
    }

    /**
     * Records the request and replays the next scripted response (an empty
     * prose response once the script runs out).
     */
    public function chat(array $messages, array $options = []): Response
    {
        $this->requests[] = ['messages' => $messages, 'options' => $options];

        return $this->responses[$this->turn++] ?? new Response('');
    }
}
