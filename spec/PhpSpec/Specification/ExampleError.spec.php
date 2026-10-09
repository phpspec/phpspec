<?php

use PhpSpec\Specification\ExampleError;

// Code under spec that reaches into PhpSpec: the error is raised beyond the spec.
define('BLAME_SPEC_OUTSIDER', sys_get_temp_dir() . '/phpspec_blame_outsider_' . getmypid() . '.php');
if (!function_exists('blame_spec_outsider_reaches_in')) {
    register_shutdown_function(static fn() => @unlink(BLAME_SPEC_OUTSIDER));
    file_put_contents(BLAME_SPEC_OUTSIDER, "<?php\nfunction blame_spec_outsider_reaches_in(): void { \\PhpSpec\\Mock\\Double::getInstance('Nope\\Missing'); }\n");
    require BLAME_SPEC_OUTSIDER;
}
describe(ExampleError::class, function() {

    it("wraps an exception", function() {
        $original = new \RuntimeException("something broke");
        $error = new ExampleError("something broke", $original);
        expect($error)->toBeAnInstanceOf(ExampleError::class);
        expect($error->getMessage())->toBe("something broke");
    });

    it("knows the error type", function() {
        $original = new \InvalidArgumentException("bad arg");
        $error = new ExampleError("bad arg", $original);
        expect($error->getType())->toBe("InvalidArgumentException");
    });

    it("provides surrounding code", function() {
        $original = new \RuntimeException("fail");
        $error = new ExampleError("fail", $original);
        $code = $error->getSurroundingCode();
        expect(count($code) > 0)->toBe(true);
    });

    it("preserves file and line from original exception", function() {
        $original = new \RuntimeException("fail");
        $error = new ExampleError("fail", $original);
        expect($error->getFile())->toBe(__FILE__);
        expect($error->getLine())->toBeOfType('int');
    });

    it("filters trace frames without file key", function() {
        try {
            array_map(function() { throw new \RuntimeException("no file"); }, [1]);
        } catch (\RuntimeException $e) {
            $error = new ExampleError($e->getMessage(), $e);
            $trace = $error->getFilteredTrace();
            expect($trace)->toBeOfType('array');
        }
    });

    it("filters trace to remove framework and vendor frames", function() {
        $original = new \RuntimeException("fail");
        $error = new ExampleError("fail", $original);
        $trace = $error->getFilteredTrace();
        expect($trace)->toBeOfType('array');

        // No frames should contain src/PhpSpec/ or vendor/
        foreach ($trace as $frame) {
            if (isset($frame['file'])) {
                expect(str_contains($frame['file'], 'src/PhpSpec/'))->toBe(false);
                expect(str_contains($frame['file'], 'vendor/'))->toBe(false);
            }
        }
    });

    it("names the class a class-not-found error is about", function() {
        $message = 'Class "App\Calculator" not found';
        $error = new ExampleError($message, new \Error($message));
        expect($error->missingClass())->toBe('App\Calculator');
    });

    it("names no class for any other error", function() {
        $error = new ExampleError("something broke", new \RuntimeException("something broke"));
        expect($error->missingClass())->toBeNull();
    });

    it("keeps the site and the frames a report carried, so blame and the spec line answer as they did where it was thrown", function() {
        $error = ExampleError::fromReport("boom", "LogicException", "/project/src/App/Basket.php", 12, [
            ['file' => '/project/src/App/Basket.php', 'line' => 12, 'function' => 'total'],
            ['file' => '/project/spec/App/Basket.spec.php', 'line' => 7, 'function' => '{closure}'],
        ]);

        expect($error->getFile())->toBe('/project/src/App/Basket.php');
        expect($error->getLine())->toBe(12);
        expect($error->blame())->toBe(['file' => '/project/src/App/Basket.php', 'line' => 12]);
        expect($error->lineIn('/project/spec/App/Basket.spec.php'))->toBe(7);
        expect($error->getFilteredTrace())->toHaveCount(2);
    });

    it("carries no site when rebuilt from a report that had none", function() {
        $error = ExampleError::fromReport("Something went wrong", "LogicException");

        expect($error->getMessage())->toBe("Something went wrong");
        expect($error->getType())->toBe("LogicException");
        expect($error->getFile())->toBe('');
        expect($error->getLine())->toBe(0);
        expect($error->getSurroundingCode())->toBe([]);
    });

    it("blames the site when the error was thrown in the spec", function() {
        $line = __LINE__ + 1;
        $error = new ExampleError("boom", new \RuntimeException("boom"));

        expect($error->blame())->toBe(['file' => __FILE__, 'line' => $line]);
    });

    it("names the line in a given file the error came through: the site when it is there, else the innermost frame in it", function() {
        try {
            $line = __LINE__ + 1;
            blame_spec_outsider_reaches_in();
        } catch (\LogicException $e) {
            $error = new ExampleError($e->getMessage(), $e);
        }

        expect($error->blame())->toBe(['file' => realpath(BLAME_SPEC_OUTSIDER), 'line' => 2]);
        expect($error->lineIn(__FILE__))->toBe($line);
        expect($error->lineIn(realpath(BLAME_SPEC_OUTSIDER)))->toBe(2);
        expect($error->lineIn('/nowhere/Else.spec.php'))->toBeNull();
    });

    it("blames the first frame in the spec when the error was thrown inside PhpSpec itself", function() {
        try {
            $line = __LINE__ + 1;
            \PhpSpec\Mock\Double::getInstance('Nope\Missing');
        } catch (\LogicException $e) {
            $error = new ExampleError($e->getMessage(), $e);
        }

        expect($error->blame())->toBe(['file' => __FILE__, 'line' => $line]);
        expect(implode("\n", $error->getSurroundingCode()))->toContain("Double::getInstance('Nope\\Missing')");
    });

});
