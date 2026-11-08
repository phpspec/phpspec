<?php

use PhpSpec\CapturedNotes;
use PhpSpec\OwnCode;
use PhpSpec\ProjectRoot;

// A project and a library it depends on, written outside the spec tree so the
// code that raised a note, and the code that called it, are told apart by
// where they live.
if (!defined('CAPTURED_NOTES_PROJECT')) {
    define('CAPTURED_NOTES_PROJECT', sys_get_temp_dir() . '/phpspec_captured_notes_' . getmypid());
}

if (!function_exists('CapturedNotesFixture\App\callsTheDeprecated')) {
    $files = [
        'vendor/acme/old/Old.php' => <<<'PHP'
            <?php
            namespace CapturedNotesFixture\Acme;

            function deprecated(): void { trigger_error('deprecated() is deprecated.', E_USER_DEPRECATED); }
            function announced(): void { trigger_deprecation('acme/old', '2.0', 'announced() is deprecated.'); }
            function warns(): void { trigger_error('the cache is cold', E_USER_WARNING); }
            function internally(): void { deprecated(); announced(); warns(); }
            PHP,
        'src/App.php' => <<<'PHP'
            <?php
            namespace CapturedNotesFixture\App;

            function callsTheDeprecated(): void { \CapturedNotesFixture\Acme\deprecated(); \CapturedNotesFixture\Acme\announced(); }
            function callsWhatCallsThem(): void { \CapturedNotesFixture\Acme\internally(); }
            PHP,
    ];

    foreach ($files as $path => $code) {
        $file = CAPTURED_NOTES_PROJECT . '/' . $path;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, $code);
        require $file;
    }

    register_shutdown_function(static function () use ($files): void {
        foreach (array_keys($files) as $path) {
            if (is_file(CAPTURED_NOTES_PROJECT . '/' . $path)) {
                unlink(CAPTURED_NOTES_PROJECT . '/' . $path);
            }
        }
    });
}

describe(CapturedNotes::class, function () {

    this('project', fn() => new OwnCode(ProjectRoot::at(CAPTURED_NOTES_PROJECT), [ProjectRoot::at(CAPTURED_NOTES_PROJECT . '/vendor')]));

    it('keeps every warning, deprecation and notice raised while it listens, with where it was raised', function () {
        $caught = new CapturedNotes(new OwnCode(ProjectRoot::at(__DIR__), []));

        $caught->listen();
        trigger_error('careful', E_USER_WARNING); $line = __LINE__;
        trigger_error('old', E_USER_DEPRECATED);
        trigger_error('for the record', E_USER_NOTICE);
        $caught->stop();

        expect($caught->notes())->toBe([
            ['severity' => E_USER_WARNING, 'message' => 'careful', 'file' => __FILE__, 'line' => $line],
            ['severity' => E_USER_DEPRECATED, 'message' => 'old', 'file' => __FILE__, 'line' => $line + 1],
            ['severity' => E_USER_NOTICE, 'message' => 'for the record', 'file' => __FILE__, 'line' => $line + 2],
        ]);
    });

    it('leaves a warning or notice the code silenced with @ to PHP, so error_get_last() still holds it', function () {
        $caught = new CapturedNotes(new OwnCode(ProjectRoot::at(__DIR__), []));

        $caught->listen();
        error_clear_last();
        @trigger_error('probing', E_USER_WARNING);
        $last = error_get_last();
        @trigger_error('not yet', E_USER_NOTICE);
        $caught->stop();

        expect($caught->notes())->toBe([]);
        expect($last['message'] ?? null)->toBe('probing');
    });

    it('keeps a deprecation silenced with @, as libraries raise theirs for the tests to see', function () {
        $caught = new CapturedNotes(new OwnCode(ProjectRoot::at(__DIR__), []));

        $caught->listen();
        @trigger_error('old', E_USER_DEPRECATED); $line = __LINE__;
        $caught->stop();

        expect($caught->notes())->toBe([['severity' => E_USER_DEPRECATED, 'message' => 'old', 'file' => __FILE__, 'line' => $line]]);
    });

    it('keeps a deprecation a library raised for the project calling it, by trigger_error or trigger_deprecation', function () {
        $caught = new CapturedNotes($this->project);

        $caught->listen();
        CapturedNotesFixture\App\callsTheDeprecated();
        $caught->stop();

        expect(array_column($caught->notes(), 'message'))->toBe([
            'deprecated() is deprecated.',
            'Since acme/old 2.0: announced() is deprecated.',
        ]);
    });

    it('leaves out a deprecation a library raised for its own code calling it, and keeps its warning', function () {
        $caught = new CapturedNotes($this->project);

        $caught->listen();
        CapturedNotesFixture\App\callsWhatCallsThem();
        $caught->stop();

        expect(array_column($caught->notes(), 'message'))->toBe(['the cache is cold']);
    });

    it('takes what the code it runs raises, keeping it across runs, and answers what the code answered', function () {
        $caught = new CapturedNotes(new OwnCode(ProjectRoot::at(CAPTURED_NOTES_PROJECT), []));

        $answer = $caught->during(function (): int {
            trigger_error('set up', E_USER_WARNING);

            return 7;
        });
        $caught->during(fn() => trigger_error('torn down', E_USER_NOTICE));

        expect($answer)->toBe(7);
        expect(array_column($caught->notes(), 'message'))->toBe(['set up', 'torn down']);
    });

    it('keeps what was raised before the code it runs threw', function () {
        $caught = new CapturedNotes(new OwnCode(ProjectRoot::at(CAPTURED_NOTES_PROJECT), []));

        expect(fn() => $caught->during(function (): void {
            trigger_error('before breaking', E_USER_WARNING);

            throw new RuntimeException('broke');
        }))->toThrow(RuntimeException::class);

        expect(array_column($caught->notes(), 'message'))->toBe(['before breaking']);
    });

    context('telling a file loading from code being called', function () {
        this('loading', fn() => function (string $source): array {
            $file = (string) tempnam(sys_get_temp_dir(), 'phpspec_loading_');
            file_put_contents($file, $source);
            $caught = new CapturedNotes(new OwnCode(ProjectRoot::at(__DIR__), []));

            try {
                $caught->during(function () use ($file): void {
                    require $file;
                });
            } finally {
                unlink($file);
            }

            return array_column($caught->notes(), 'loading', 'message');
        });

        it('marks what a file raises at its top level, whether directly or through a function it calls', function () {
            $function = 'loading_note_' . bin2hex(random_bytes(6));

            expect(($this->loading)("<?php\ntrigger_error('at the top', E_USER_WARNING);\n"))->toBe(['at the top' => true]);
            expect(($this->loading)("<?php\nfunction {$function}() { trigger_error('through a function', E_USER_WARNING); }\n{$function}();\n"))->toBe(['through a function' => true]);
        });

        it('marks what PHP says as it compiles a file', function () {
            expect(($this->loading)("<?php\nuse RuntimeException;\n"))->toBe(["The use statement with non-compound name 'RuntimeException' has no effect" => true]);
        });

        it('leaves unmarked what code an example called raises, a closure being what it ran in', function () {
            $caught = new CapturedNotes(new OwnCode(ProjectRoot::at(__DIR__), []));

            $caught->during(fn() => trigger_error('called', E_USER_WARNING));

            expect($caught->notes()[0])->not()->toHaveKey('loading');
        });
    });
});
