<?php

use PhpSpec\ByReturnValue;
use PhpSpec\CapturedNotes;
use PhpSpec\OwnCode;
use PhpSpec\ProjectRoot;

describe(ByReturnValue::class, function () {
    it('answers what the call returns, the warning it raises reaching no handler but its own', function () {
        $outer = new CapturedNotes(new OwnCode(ProjectRoot::at(__DIR__), []));
        $outer->listen();

        $answer = (new ByReturnValue())->call(function (): bool {
            trigger_error('cannot stat it', E_USER_WARNING);

            return false;
        });
        trigger_error('after it', E_USER_NOTICE);
        $outer->stop();

        expect($answer)->toBeFalse();
        expect(array_column($outer->notes(), 'message'))->toBe(['after it']);
    });

    it('leaves the code of phpspec silencing nothing with @', function () {
        $silenced = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../../src', FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                foreach (PhpToken::tokenize((string) file_get_contents($file->getPathname())) as $token) {
                    if ($token->text === '@') {
                        $silenced[] = basename($file->getPathname()) . ':' . $token->line;
                    }
                }
            }
        }

        expect($silenced)->toBe([]);
    });
});
