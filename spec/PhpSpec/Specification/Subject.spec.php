<?php

use PhpSpec\Specification\Subject;

describe(Subject::class, function () {

    it('re-executes the spec file on every construction, not just the first', function () {
        $counterFile = sys_get_temp_dir() . '/phpspec_subject_counter_' . uniqid() . '.txt';
        $file = sys_get_temp_dir() . '/phpspec_subject_test_' . uniqid() . '.php';
        file_put_contents($counterFile, '0');
        file_put_contents($file, sprintf(
            '<?php file_put_contents(%s, (int) file_get_contents(%s) + 1);',
            var_export($counterFile, true),
            var_export($counterFile, true),
        ));

        try {
            new Subject($file);
            new Subject($file);

            expect((int) file_get_contents($counterFile))->toBe(2);
        } finally {
            unlink($file);
            unlink($counterFile);
        }
    });

    it('propagates errors thrown while loading the spec file', function () {
        $file = sys_get_temp_dir() . '/phpspec_subject_error_test_' . uniqid() . '.php';
        file_put_contents($file, "<?php\nthrow new \\RuntimeException('boom');\n");
        // `->toThrow()` evaluates the closure after this callback returns, so
        // cleanup can't sit in a try/finally around it without deleting the
        // file before the deferred assertion runs it.
        register_shutdown_function(fn() => @unlink($file));

        expect(fn() => new Subject($file))->toThrow(\RuntimeException::class);
    });
});
