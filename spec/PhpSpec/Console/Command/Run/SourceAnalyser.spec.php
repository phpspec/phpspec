<?php

use PhpSpec\Console\Command\Run\SourceAnalyser;

describe(SourceAnalyser::class, function () {

    let('analyser', fn() => new SourceAnalyser());

    context('countArguments', function () {

        it('returns 0 for empty string', function () {
            expect($this->analyser->countArguments(''))->toBe(0);
        });

        it('counts simple arguments', function () {
            expect($this->analyser->countArguments("'a', 'b', 'c'"))->toBe(3);
        });

        it('handles nested parentheses', function () {
            expect($this->analyser->countArguments("fn(1, 2), 'b'"))->toBe(2);
        });

        it('handles nested brackets', function () {
            expect($this->analyser->countArguments("[1, 2], 'b'"))->toBe(2);
        });

        it('handles nested braces', function () {
            expect($this->analyser->countArguments("fn() { return 1; }, 'b'"))->toBe(2);
        });

        it('returns 1 for single argument', function () {
            expect($this->analyser->countArguments("'hello'"))->toBe(1);
        });
    });

    context('extractArgumentCount', function () {

        it('extracts argument count from file', function () {
            $count = $this->analyser->extractArgumentCount(__FILE__, __LINE__, 'extractArgumentCount');
            expect($count)->toBeOfType('int');
        });

        it('returns 0 for non-existent file', function () {
            $count = $this->analyser->extractArgumentCount('/nonexistent/file.php', 1, 'foo');
            expect($count)->toBe(0);
        });

        it('returns 0 when method call not found on line', function () {
            $tmpFile = tempnam(sys_get_temp_dir(), 'phpspec_test_');
            file_put_contents($tmpFile, "<?php\n\$x = 42;\n");

            $count = $this->analyser->extractArgumentCount($tmpFile, 2, 'nonExistentMethod');
            unlink($tmpFile);
            expect($count)->toBe(0);
        });
    });

    context('resolveVariableClass', function () {

        it('resolves variable from direct assignment', function () {
            $lines = [
                '$calc = new App\\Calculator();' . "\n",
                'expect($calc->add(1, 2))->toBe(3);' . "\n",
            ];

            $result = $this->analyser->resolveVariableClass($lines, 1, 'calc');
            expect($result)->toBe('App\\Calculator');
        });

        it('resolves variable from let() binding', function () {
            $lines = [
                "use App\\Calculator;\n",
                "describe(Calculator::class, function() {\n",
                "    let(\"calc\", fn() => new Calculator());\n",
                "    it(\"adds\", fn() => expect(\$this->calc->add(1,2))->toBe(3));\n",
            ];

            $result = $this->analyser->resolveVariableClass($lines, 3, 'calc');
            expect($result)->toBe('App\\Calculator');
        });

        it('resolves short name via use import from let()', function () {
            $lines = [
                "use Rafael\\Duarte;\n",
                "let(\"duarte\", fn() => new Duarte());\n",
                "expect(\$this->duarte->greet())->toBe(\"hi\");\n",
            ];

            $result = $this->analyser->resolveVariableClass($lines, 2, 'duarte');
            expect($result)->toBe('Rafael\\Duarte');
        });

        it('returns null when variable not found', function () {
            $lines = [
                'expect($unknown->foo())->toBe(1);' . "\n",
            ];

            $result = $this->analyser->resolveVariableClass($lines, 0, 'unknown');
            expect($result)->toBeNull();
        });
    });

    context('resolveUseImport', function () {

        it('resolves short name to FQCN via use statement', function () {
            $lines = [
                "use App\\Service\\Mailer;\n",
                "use App\\Model\\User;\n",
            ];

            expect($this->analyser->resolveUseImport($lines, 'Mailer'))->toBe('App\\Service\\Mailer');
            expect($this->analyser->resolveUseImport($lines, 'User'))->toBe('App\\Model\\User');
        });

        it('returns short name when no use import found', function () {
            $lines = ["use Other\\Thing;\n"];

            expect($this->analyser->resolveUseImport($lines, 'NotImported'))->toBe('NotImported');
        });
    });

    context('extractExpectedReturnValue', function () {

        it('extracts toBe value from file', function () {
            // Create a temp file with a toBe assertion
            $tmpFile = tempnam(sys_get_temp_dir(), 'phpspec_test_');
            file_put_contents($tmpFile, "<?php\nexpect(\$calc->add(1, 2))->toBe(3);\n");

            $result = $this->analyser->extractExpectedReturnValue($tmpFile, 2, 'add');
            unlink($tmpFile);
            expect($result)->toBe('3');
        });

        it('returns null for non-existent file', function () {
            $result = $this->analyser->extractExpectedReturnValue('/nonexistent/file.php', 1, 'foo');
            expect($result)->toBeNull();
        });

        it('returns null when no toBe found', function () {
            $tmpFile = tempnam(sys_get_temp_dir(), 'phpspec_test_');
            file_put_contents($tmpFile, "<?php\nexpect(\$calc->add(1, 2))->toBeGreaterThan(0);\n");

            $result = $this->analyser->extractExpectedReturnValue($tmpFile, 2, 'add');
            unlink($tmpFile);
            expect($result)->toBeNull();
        });
    });

});
