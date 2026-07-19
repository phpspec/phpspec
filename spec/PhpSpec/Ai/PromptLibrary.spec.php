<?php

use PhpSpec\Ai\PromptLibrary;
use PhpSpec\Filesystem;

describe(PromptLibrary::class, function () {

    it('reads a prompt artifact by name from the Prompts directory', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturnUsing(fn(string $p): string => str_ends_with($p, '/Prompts/next.txt') ? 'OUTSIDE-IN coaching' : '');
        $library = new PromptLibrary($fs);

        expect($library->read('next'))->toBe('OUTSIDE-IN coaching');
    });

    it('returns an empty string when the artifact is absent', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        $library = new PromptLibrary($fs);

        expect($library->read('missing'))->toBe('');
    });

});
