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

    it('replaces only the system slot when re-oriented for another command', function () {
        $transcript = new Transcript();
        $transcript->orient('navigator', 'NAV CONTRACT');
        $transcript->say('add a spec');
        $transcript->heard(new Response('on it'));
        $transcript->orient('driver', 'DRIVER CONTRACT');

        $messages = $transcript->messages();

        expect($messages[0]->content)->toBe('DRIVER CONTRACT');
        expect($messages[1]->content)->toBe('add a spec');
        expect($messages[2]->content)->toBe('on it');
        expect(count($messages))->toBe(3);
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

    it('prunes superseded situations at turn start', function () {
        $transcript = new Transcript();
        $transcript->orient('navigator', 'NAV CONTRACT');
        $transcript->situate('SUITE: red');
        $transcript->say('what now?');
        $transcript->beginTurn();

        $contents = array_map(fn(Message $message) => is_string($message->content) ? $message->content : '', $transcript->messages());

        expect(implode("\n", $contents))->not()->toContain('[Current situation]');
        expect($contents)->toContain('what now?');
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
