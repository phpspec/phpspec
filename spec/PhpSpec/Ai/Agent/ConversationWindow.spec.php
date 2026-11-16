<?php

use PhpSpec\Ai\Message;
use PhpSpec\Ai\Role;
use PhpSpec\Ai\ToolCall;
use PhpSpec\Ai\Agent\ConversationWindow;

describe(ConversationWindow::class, function () {

    it('leaves a history within its budget exactly as it is, situations and long tool output included', function () {
        $window = new ConversationWindow(recentMessages: 2, compactThreshold: 999, maxContextTokens: 999999);
        $messages = [
            Message::system('SYS'),
            Message::user("[Current situation]\nSUITE: red"),
            Message::user('input 1'),
            Message::assistant('', [new ToolCall('t1', 'read_file', ['path' => 'a'])]),
            Message::toolResult('t1', str_repeat('x', 5000)),
            Message::user("[Current situation]\nSUITE: green"),
            Message::user('input 2'),
        ];

        expect($window->apply($messages))->toBe($messages);
    });

    it('leaves a short history untouched', function () {
        $window = new ConversationWindow(recentMessages: 4, compactThreshold: 10);
        $messages = [Message::system('SYS'), Message::user('a'), Message::assistant('ok')];

        $result = $window->apply($messages);

        expect(count($result))->toBe(3);
        expect($result[1]->content)->toBe('a');
    });

    it('folds the oldest turns into a summary past the threshold, keeping system and recent turns', function () {
        $window = new ConversationWindow(recentMessages: 4, compactThreshold: 10, maxContextTokens: 999999);

        $messages = [Message::system('SYS')];
        for ($i = 1; $i <= 6; $i++) {
            $messages[] = Message::user("input $i");
            $messages[] = Message::assistant('', [new ToolCall("t$i", 'write_file', ['path' => "src/App/F$i.php"])]);
            $messages[] = Message::toolResult("t$i", "File written to src/App/F$i.php");
        }

        $result = $window->apply($messages);
        $contents = implode("\n", array_map(fn($m) => is_string($m->content) ? $m->content : '', $result));

        // The system prompt is preserved at the head.
        expect($result[0]->role)->toBe(Role::System);
        expect($result[0]->content)->toBe('SYS');

        // A rolling summary replaces the oldest turns, and lists their artifacts.
        expect($result[1]->role)->toBe(Role::User);
        expect($result[1]->content)->toContain('[Earlier in this session]');
        expect($result[1]->content)->toContain('src/App/F1.php');

        // The retained tail begins on a clean user boundary (no orphan tool result).
        expect($result[2]->role)->toBe(Role::User);

        // Recent turns survive verbatim; the whole thing is smaller.
        expect($contents)->toContain('input 6');
        expect(count($result))->toBeLessThan(count($messages));
    });

    it('records the last suite state seen in the summary', function () {
        $window = new ConversationWindow(recentMessages: 2, compactThreshold: 6, maxContextTokens: 999999);

        $messages = [Message::system('SYS')];
        for ($i = 1; $i <= 4; $i++) {
            $messages[] = Message::user("input $i");
            $messages[] = Message::user("[Auto-verify after your change]\nSUITE: green \u{2014} run $i");
        }

        $result = $window->apply($messages);

        expect($result[1]->content)->toContain('Last suite state seen: SUITE: green');
    });

    it('skips compaction when the recent tail has no clean user boundary', function () {
        // A single very long turn: no user message in the recent window to cut on.
        $window = new ConversationWindow(recentMessages: 2, compactThreshold: 4, maxContextTokens: 999999);
        $messages = [
            Message::system('SYS'),
            Message::user('one input'),
            Message::assistant('', [new ToolCall('t1', 'read_file', ['path' => 'a'])]),
            Message::toolResult('t1', 'r1'),
            Message::assistant('', [new ToolCall('t2', 'read_file', ['path' => 'b'])]),
            Message::toolResult('t2', 'r2'),
        ];

        $result = $window->apply($messages);

        // Nothing folded: no summary, history intact rather than split mid-turn.
        expect(implode("\n", array_map(fn($m) => is_string($m->content) ? $m->content : '', $result)))
            ->not()->toContain('[Earlier in this session]');
        expect(count($result))->toBe(6);
    });

    it('starts the compacted conversation from the system prompt in force where it cuts, so a swapped role survives', function () {
        $window = new ConversationWindow(recentMessages: 2, compactThreshold: 8, maxContextTokens: 999999);
        $messages = [Message::system('NAV CONTRACT'), Message::user('swap'), Message::system('DRIVER CONTRACT')];
        for ($i = 1; $i <= 4; $i++) {
            $messages[] = Message::user("input $i");
            $messages[] = Message::assistant("answer $i");
        }

        $result = $window->apply($messages);

        expect($result[0]->content)->toBe('DRIVER CONTRACT');
        expect(array_filter($result, fn(Message $message) => $message->role === Role::System))->toHaveLength(1);
    });

    it('leaves the history alone when the cut would fold nothing but the system prompt', function () {
        $window = new ConversationWindow(recentMessages: 4, compactThreshold: 2, maxContextTokens: 999999);
        $messages = [Message::system('SYS'), Message::user('one'), Message::assistant('a'), Message::assistant('b')];

        expect($window->apply($messages))->toBe($messages);
    });

});
