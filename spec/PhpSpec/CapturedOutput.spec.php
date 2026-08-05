<?php

use PhpSpec\CapturedOutput;

describe(CapturedOutput::class, function () {

    it('keeps what the body printed instead of letting it out', function () {
        $captured = new CapturedOutput();

        ob_start();
        $captured->around(function () {
            echo "printed by the subject\n";
        });
        $leaked = ob_get_clean();

        expect($captured->text())->toBe("printed by the subject\n");
        expect($leaked)->toBe('');
    });

    it('has nothing to show for a body that printed nothing', function () {
        $captured = new CapturedOutput();
        $captured->around(function () {});

        expect($captured->text())->toBe('');
    });

    it('keeps what a throwing body printed, and lets the throw through', function () {
        $captured = new CapturedOutput();

        $thrown = null;

        try {
            $captured->around(function () {
                echo 'as far as it got';

                throw new RuntimeException('boom');
            });
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        expect($thrown?->getMessage())->toBe('boom');
        expect($captured->text())->toBe('as far as it got');
    });

    it('takes back a buffer the body opened and forgot to close', function () {
        $captured = new CapturedOutput();
        $depth = ob_get_level();

        $captured->around(function () {
            echo 'before';
            ob_start();
            echo 'inside a buffer of its own';
        });

        expect($captured->text())->toBe('beforeinside a buffer of its own');
        expect(ob_get_level())->toBe($depth);
    });

    it('survives a body that closed one buffer too many', function () {
        $captured = new CapturedOutput();

        ob_start();
        $captured->around(function () {
            echo 'gone with the buffer';
            ob_end_clean();
        });
        ob_end_clean();

        expect($captured->text())->toBe('');
    });

    it('adds up what every body it ran printed', function () {
        $captured = new CapturedOutput();
        $captured->around(function () {
            echo 'one ';
        });
        $captured->around(function () {
            echo 'two';
        });

        expect($captured->text())->toBe('one two');
    });

});
