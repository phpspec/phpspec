<?php

use PhpSpec\Console\Command\Refactor\Diff;

describe(Diff::class, function () {

    context('compute', function () {

        it('returns empty for identical content', function () {
            $result = Diff::compute(['line1', 'line2'], ['line1', 'line2']);

            expect($result)->toHaveCount(2);
            expect($result[0]['type'])->toBe(' ');
            expect($result[1]['type'])->toBe(' ');
        });

        it('detects added lines', function () {
            $result = Diff::compute(['line1'], ['line1', 'line2']);

            $types = array_column($result, 'type');
            expect($types)->toContain('+');
        });

        it('detects removed lines', function () {
            $result = Diff::compute(['line1', 'line2'], ['line1']);

            $types = array_column($result, 'type');
            expect($types)->toContain('-');
        });

        it('handles completely different content', function () {
            $result = Diff::compute(['old'], ['new']);

            expect($result)->toHaveCount(2);
            $types = array_column($result, 'type');
            expect($types)->toContain('-');
            expect($types)->toContain('+');
        });

        it('handles empty old content', function () {
            $result = Diff::compute([], ['line1', 'line2']);

            expect($result)->toHaveCount(2);
            expect($result[0]['type'])->toBe('+');
            expect($result[1]['type'])->toBe('+');
        });

        it('handles empty new content', function () {
            $result = Diff::compute(['line1', 'line2'], []);

            expect($result)->toHaveCount(2);
            expect($result[0]['type'])->toBe('-');
            expect($result[1]['type'])->toBe('-');
        });

        it('handles both empty', function () {
            $result = Diff::compute([], []);

            expect($result)->toHaveCount(0);
        });

        it('anchors an appended block on the new lines, not identical preceding ones', function () {
            $old = [
                'class Calculator',
                '{',
                '    public function subtract()',
                '    {',
                '    }',
                '}',
            ];
            $new = [
                'class Calculator',
                '{',
                '    public function subtract()',
                '    {',
                '    }',
                '',
                '    public function total()',
                '    {',
                '    }',
                '}',
            ];

            $result = Diff::compute($old, $new);

            $added = array_values(array_map(
                fn($e) => $e['text'],
                array_filter($result, fn($e) => $e['type'] === '+'),
            ));
            $addedLines = array_values(array_map(
                fn($e) => $e['line'],
                array_filter($result, fn($e) => $e['type'] === '+'),
            ));

            expect($added)->toBe([
                '',
                '    public function total()',
                '    {',
                '    }',
            ]);
            expect($addedLines)->toBe([6, 7, 8, 9]);
        });

        it('reconstructs the new file from context and added lines after sliding', function () {
            $old = ['a', 'x', 'y', 'a'];
            $new = ['a', 'x', 'y', 'a', 'x', 'y', 'a'];

            $result = Diff::compute($old, $new);

            $rebuilt = array_map(
                fn($e) => $e['text'],
                array_filter($result, fn($e) => $e['type'] !== '-'),
            );
            expect(array_values($rebuilt))->toBe($new);
            // no spurious deletions when purely appending
            expect(array_column($result, 'type'))->not()->toContain('-');
        });
    });

    context('format', function () {

        it('formats added lines in green', function () {
            $diff = [['type' => '+', 'line' => 1, 'text' => 'new line']];
            $output = Diff::format($diff);

            expect($output)->toContain('<fg=green>');
            expect($output)->toContain('+ new line');
        });

        it('formats removed lines in red', function () {
            $diff = [['type' => '-', 'line' => 1, 'text' => 'old line']];
            $output = Diff::format($diff);

            expect($output)->toContain('<fg=red>');
            expect($output)->toContain('- old line');
        });

        it('formats unchanged lines near a change in gray', function () {
            $diff = [
                ['type' => ' ', 'line' => 1, 'text' => 'same line'],
                ['type' => '+', 'line' => 2, 'text' => 'new line'],
            ];
            $output = Diff::format($diff);

            expect($output)->toContain('<fg=gray>');
            expect($output)->toContain('same line');
        });

        it('shows only three unchanged lines around a change, never the whole file', function () {
            $old = array_map(fn(int $n) => sprintf('line %02d', $n), range(1, 20));
            $new = $old;
            array_splice($new, 11, 0, ['inserted a', 'inserted b']);

            $output = Diff::format(Diff::compute($old, $new));

            expect($output)->not()->toContain('line 01');
            expect($output)->not()->toContain('line 08');
            expect($output)->toContain('line 09');    // three before the insertion
            expect($output)->toContain('+ inserted a');
            expect($output)->toContain('line 14');    // three after
            expect($output)->not()->toContain('line 15');
            expect($output)->not()->toContain('...');
        });

        it('elides the stretch between distant changes to a single line', function () {
            $old = array_map(fn(int $n) => sprintf('line %02d', $n), range(1, 40));
            $new = $old;
            $new[2] = 'changed near the top';
            $new[35] = 'changed near the bottom';

            $output = Diff::format(Diff::compute($old, $new));

            expect($output)->toContain('changed near the top');
            expect($output)->toContain('changed near the bottom');
            expect($output)->not()->toContain('line 20');
            expect(substr_count($output, '...'))->toBe(1);
        });

        it('keeps nearby changes in one uninterrupted stretch', function () {
            $old = array_map(fn(int $n) => sprintf('line %02d', $n), range(1, 12));
            $new = $old;
            $new[3] = 'first change';
            $new[7] = 'second change';

            $output = Diff::format(Diff::compute($old, $new));

            expect($output)->toContain('first change');
            expect($output)->toContain('second change');
            expect($output)->toContain('line 06');     // the short gap stays visible
            expect($output)->not()->toContain('...');
        });

        it('shows a new file in full: every line is a change', function () {
            $new = array_map(fn(int $n) => "line $n", range(1, 10));

            $output = Diff::format(Diff::compute([], $new));

            expect($output)->toContain('+ line 1');
            expect($output)->toContain('+ line 10');
            expect($output)->not()->toContain('...');
        });

        it('pads line numbers to 4 characters', function () {
            $diff = [['type' => '+', 'line' => 5, 'text' => 'line']];
            $output = Diff::format($diff);

            expect($output)->toContain('   5');
        });

        it('formats empty diff as empty string', function () {
            $output = Diff::format([]);

            expect($output)->toBe('');
        });
    });
});
