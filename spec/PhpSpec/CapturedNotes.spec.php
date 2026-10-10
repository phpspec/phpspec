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

    let('project', fn() => new OwnCode(ProjectRoot::at(CAPTURED_NOTES_PROJECT), [ProjectRoot::at(CAPTURED_NOTES_PROJECT . '/vendor')]));

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
});
