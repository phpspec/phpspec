<?php

use PhpSpec\Console\Prompt;

describe(Prompt::class, function () {

    it('reads each answer from the stream it is given, and nothing once it ends', function () {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "y\n\n");
        rewind($stream);
        $prompt = new Prompt($stream);

        expect($prompt->ask('  > '))->toBe('y');
        expect($prompt->ask('  > '))->toBe('');
        expect($prompt->ask('  > '))->toBeNull();
    });
});
