<?php

use PhpSpec\Specification\Subject;

describe(Subject::class, function () {

    it('is a bare world when constructed without a file', function () {
        $subject = new Subject();
        $subject->foo = 'bar';

        expect($subject->foo)->toBe('bar');
    });

    it('runs a spec file with $this bound to itself, so top-level closures capture it as their world', function () {
        $file = sys_get_temp_dir() . '/phpspec_subject_bind_' . uniqid() . '.php';
        file_put_contents($file, "<?php\n\$GLOBALS['phpspec_subject_probe'] = function () { return \$this; };\n");
        register_shutdown_function(fn() => @unlink($file));

        $subject = new Subject();
        $subject->load($file);

        $captured = (new ReflectionFunction($GLOBALS['phpspec_subject_probe']))->getClosureThis();
        expect($captured)->toBe($subject);
    });

    it('propagates errors thrown while loading a spec file', function () {
        $file = sys_get_temp_dir() . '/phpspec_subject_error_' . uniqid() . '.php';
        file_put_contents($file, "<?php\nthrow new \\RuntimeException('boom');\n");
        register_shutdown_function(fn() => @unlink($file));

        expect(fn() => (new Subject())->load($file))->toThrow(\RuntimeException::class);
    });
});
