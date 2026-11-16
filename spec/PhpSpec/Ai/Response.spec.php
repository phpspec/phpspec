<?php

use PhpSpec\Ai\Response;
use PhpSpec\Ai\Role;
use PhpSpec\Ai\ToolCall;

describe(Response::class, function () {

    this('replay', fn() => ['anthropic' => ['blocks' => [['type' => 'thinking', 'thinking' => '', 'signature' => 'sig']]]]);

    it('continues the conversation as the assistant message, carrying what the provider needs back', function () {
        $call = new ToolCall('t1', 'run_specs', []);

        $message = (new Response('Running them.', [$call], 12, $this->replay))->toMessage();

        expect($message->role)->toBe(Role::Assistant);
        expect($message->content)->toBe('Running them.');
        expect($message->toolCalls)->toBe([$call]);
        expect($message->replay)->toBe($this->replay);
    });

    it('continues with no tool calls when it made none', function () {
        expect((new Response('Done.'))->toMessage()->toolCalls)->toBeNull();
    });

    it('records its text, its tool calls and what the provider needs back, only when there is any', function () {
        $call = new ToolCall('t1', 'read_file', ['path' => 'src/App/Calculator.php']);

        expect((new Response('Reading.', [$call], 0, $this->replay))->recorded())->toBe([
            'text' => 'Reading.',
            'tool_calls' => [['id' => 't1', 'name' => 'read_file', 'arguments' => ['path' => 'src/App/Calculator.php']]],
            'replay' => $this->replay,
        ]);
        expect((new Response('Done.'))->recorded())->toBe(['text' => 'Done.', 'tool_calls' => []]);
    });
});
