<?php

use PhpSpec\Parallel\Wire;
use PhpSpec\Parallel\WireFormatter;
use PhpSpec\Parallel\WorkQueue;
use Symfony\Component\Console\Output\BufferedOutput;

describe(WorkQueue::class, function () {

    this('handing', fn() => function (string ...$lines) {
        $input = fopen('php://memory', 'r+');
        fwrite($input, implode('', array_map(static fn(string $line): string => $line . "\n", $lines)));
        rewind($input);

        return $input;
    });

    it('hands over each file as the targets the loader takes, saying it ran one once through with it', function () {
        $wire = new Wire();
        $output = new BufferedOutput();
        $queue = new WorkQueue(($this->handing)($wire->give(['spec/A.spec.php:3', 'spec/A.spec.php:9']), $wire->give(['spec/B.spec.php'])), new WireFormatter($output));
        $handed = [];

        foreach ($queue as $targets) {
            $handed[] = [$targets, $output->fetch()];
        }

        expect($handed)->toBe([['spec/A.spec.php:3,spec/A.spec.php:9', ''], ['spec/B.spec.php', $wire->ran() . "\n"]]);
        expect($output->fetch())->toBe($wire->ran() . "\n");
    });

    it('passes over a line that hands it nothing', function () {
        $wire = new Wire();
        $queue = new WorkQueue(($this->handing)('not the wire', $wire->ran(), $wire->give(['spec/A.spec.php'])), new WireFormatter(new BufferedOutput()));

        expect(iterator_to_array($queue, false))->toBe(['spec/A.spec.php']);
    });
});
