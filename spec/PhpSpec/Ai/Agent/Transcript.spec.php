<?php

use PhpSpec\Ai\Agent\Transcript;
use PhpSpec\Ai\Message;
use PhpSpec\Ai\Response;
use PhpSpec\Ai\Role;
use PhpSpec\Ai\ToolCall;

describe(Transcript::class, function () {

    it('keeps the system message first and appends it only once per command', function () {
        $transcript = new Transcript();
        $transcript->orient('navigator', 'NAV CONTRACT');
        $transcript->say('hello');
        $transcript->orient('navigator', 'NAV CONTRACT REBUILT');

        $messages = $transcript->messages();

        expect($messages[0]->role)->toBe(Role::System);
        expect($messages[0]->content)->toBe('NAV CONTRACT');
        expect(count($messages))->toBe(2);
    });

    it('says another command\'s system prompt after what came before when re-oriented, changing nothing earlier', function () {
        $transcript = new Transcript();
        $transcript->orient('navigator', 'NAV CONTRACT');
        $transcript->say('add a spec');
        $transcript->heard(new Response('on it'));
        $transcript->say('you drive now');
        $transcript->orient('driver', 'DRIVER CONTRACT');

        $messages = $transcript->messages();

        expect(array_map(fn(Message $message) => $message->content, $messages))->toBe(['NAV CONTRACT', 'add a spec', 'on it', 'you drive now', 'DRIVER CONTRACT']);
        expect($messages[4]->role)->toBe(Role::System);
    });

    it('answers whether it is oriented for a command', function () {
        $transcript = new Transcript();

        expect($transcript->isOrientedFor('navigator'))->toBe(false);

        $transcript->orient('navigator', 'NAV CONTRACT');

        expect($transcript->isOrientedFor('navigator'))->toBe(true);
        expect($transcript->isOrientedFor('driver'))->toBe(false);
    });

    it('marks a situation as the grounding the window recognises', function () {
        $transcript = new Transcript();
        $transcript->orient('navigator', 'NAV CONTRACT');
        $transcript->situate('SUITE: green');

        $messages = $transcript->messages();

        expect($messages[1]->role)->toBe(Role::User);
        expect($messages[1]->content)->toBe("[Current situation]\nSUITE: green");
    });

    it('keeps every situation it was given, so a turn only adds to the conversation', function () {
        $transcript = new Transcript();
        $transcript->orient('navigator', 'NAV CONTRACT');
        $transcript->situate('SUITE: red');
        $transcript->say('what now?');
        $before = $transcript->messages();
        $transcript->beginTurn();

        expect($transcript->messages())->toBe($before);
    });

    it('keeps tool_use and tool_result paired when recording a round', function () {
        $transcript = new Transcript();
        $transcript->orient('driver', 'DRIVER CONTRACT');
        $transcript->say('write the spec');
        $transcript->heard(new Response('', [new ToolCall('t1', 'describe', ['class' => 'App\\Basket'])]));
        $transcript->observed('t1', 'Spec skeleton for App\\Basket written');

        $messages = $transcript->messages();

        expect($messages[2]->role)->toBe(Role::Assistant);
        expect($messages[2]->toolCalls[0]->name)->toBe('describe');
        expect($messages[3]->role)->toBe(Role::Tool);
        expect($messages[3]->toolCallId)->toBe('t1');
    });
});
