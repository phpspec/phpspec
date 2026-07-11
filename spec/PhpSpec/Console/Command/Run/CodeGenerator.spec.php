<?php

use PhpSpec\Console\Command\Pair\Chooser;
use PhpSpec\Console\Command\Pair\PairOutput;
use PhpSpec\Console\Command\Run\CodeGenerator;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\StepResult;
use Symfony\Component\Console\Output\BufferedOutput;

describe(CodeGenerator::class, function () {

    let('generator', fn() => new CodeGenerator('src', 'spec'));
    let('output', fn() => new BufferedOutput());

    context('generate with empty results', function () {

        it('produces no output when suite has no specs', function () {
            $suite = new SuiteResult([]);
            $this->generator->generate($this->output, $suite, false);
            expect($this->output->fetch())->toBe('');
        });

        it('produces no output when all examples pass', function () {
            $match = MatchResult::passed(42, null, 'OK');
            $example = new ExampleResult('test', [$match]);
            $spec = new SpecificationResult('spec', [$example]);
            $suite = new SuiteResult([$spec]);

            $this->generator->generate($this->output, $suite, false);
            expect($this->output->fetch())->toBe('');
        });

        it('produces no output in fake mode when all examples pass', function () {
            $match = MatchResult::passed(42, null, 'OK');
            $example = new ExampleResult('test', [$match]);
            $spec = new SpecificationResult('spec', [$example]);
            $suite = new SuiteResult([$spec]);

            $this->generator->generate($this->output, $suite, true);
            expect($this->output->fetch())->toBe('');
        });

        it('produces no output for nested contexts with no errors', function () {
            $example = new ExampleResult('test', []);
            $ctx = new ContextResult('ctx', [$example]);
            $spec = new SpecificationResult('spec', [$ctx]);
            $suite = new SuiteResult([$spec]);

            $this->generator->generate($this->output, $suite, false);
            expect($this->output->fetch())->toBe('');
        });
    });

    context('generate with feature results', function () {

        it('produces no output when all steps pass', function () {
            $step = new StepResult('Given something', 'passed');
            $scenario = new ScenarioResult('Test', [$step]);
            $feature = new FeatureResult('Feature', [$scenario], '/features/test.feature');
            $suite = new SuiteResult([$feature]);

            $this->generator->generate($this->output, $suite, false);
            expect($this->output->fetch())->toBe('');
        });
    });

    context('generate skips non-matching errors', function () {

        it('produces no output for non-mock non-method errors', function () {
            $error = new \PhpSpec\Specification\ExampleError(
                'Some random error',
                new \RuntimeException('Some random error'),
            );
            $example = new ExampleResult('test', [], isError: true);
            $example->setError($error);
            $spec = new SpecificationResult('spec', [$example]);
            $suite = new SuiteResult([$spec]);

            $this->generator->generate($this->output, $suite, false);
            expect($this->output->fetch())->toBe('');
        });
    });

    context('generates method stubs for undefined methods', function () {

        it('generates method and shows diff for undefined class method', function () {
            // Use paths relative to getcwd() because ClassGenerator::resolveFqcn prepends getcwd()
            $relDir = '.tmp_codegen_test_' . getmypid();
            $absDir = getcwd() . '/' . $relDir;
            @mkdir($absDir . '/src/CgTest', 0777, true);
            @mkdir($absDir . '/spec/CgTest', 0777, true);

            $classFile = $absDir . '/src/CgTest/Widget.php';
            file_put_contents($classFile, "<?php\n\nnamespace CgTest;\n\nclass Widget\n{\n}\n");

            $specFile = $absDir . '/spec/CgTest/Widget.spec.php';
            file_put_contents($specFile, "<?php\nit('works', fn() => expect(\$this->widget->spin())->toBe(true));\n");

            $generator = new CodeGenerator($relDir . '/src', $relDir . '/spec', false);

            $original = eval("return new \\Error('Call to undefined method CgTest\\\\Widget::spin()');");
            $error = new \PhpSpec\Specification\ExampleError(
                'Call to undefined method CgTest\Widget::spin()',
                $original,
            );
            $ref = new \ReflectionProperty(\Exception::class, 'file');
            $ref->setValue($error, $specFile);
            $ref = new \ReflectionProperty(\Exception::class, 'line');
            $ref->setValue($error, 2);

            $example = new ExampleResult('works', [], isError: true);
            $example->setError($error);
            $spec = new SpecificationResult('CgTest\Widget', [$example]);
            $suite = new SuiteResult([$spec]);

            $generator->generate($this->output, $suite, false);
            $out = $this->output->fetch();

            expect($out)->toContain('Do you want me to create');
            expect($out)->toContain('spin()');
            expect($out)->toContain('[MODIFIED]');
            expect($out)->toContain('public function spin');

            // Cleanup
            array_map('unlink', glob($absDir . '/src/CgTest/*'));
            array_map('unlink', glob($absDir . '/spec/CgTest/*'));
            @rmdir($absDir . '/src/CgTest');
            @rmdir($absDir . '/spec/CgTest');
            @rmdir($absDir . '/src');
            @rmdir($absDir . '/spec');
            @rmdir($absDir);
        });

        it('shows NEW FILE diff for generated interface', function () {
            $relDir = '.tmp_codegen_test_' . getmypid();
            $absDir = getcwd() . '/' . $relDir;
            @mkdir($absDir . '/src', 0777, true);
            @mkdir($absDir . '/spec', 0777, true);

            $specFile = $absDir . '/spec/test.spec.php';
            file_put_contents($specFile, "<?php\nit('mocks', function(CgTest2\\Repo \$mock) {});\n");

            $generator = new CodeGenerator($relDir . '/src', $relDir . '/spec', false);

            $original = new \RuntimeException("Cannot create mock: class or interface 'CgTest2\\Repo' does not exist");
            $error = new \PhpSpec\Specification\ExampleError(
                "Cannot create mock: class or interface 'CgTest2\\Repo' does not exist",
                $original,
            );
            $ref = new \ReflectionProperty(\Exception::class, 'file');
            $ref->setValue($error, $specFile);
            $ref = new \ReflectionProperty(\Exception::class, 'line');
            $ref->setValue($error, 2);

            $example = new ExampleResult('mocks', [], isError: true);
            $example->setError($error);
            $spec = new SpecificationResult('Test', [$example]);
            $suite = new SuiteResult([$spec]);

            $generator->generate($this->output, $suite, false);
            $out = $this->output->fetch();

            expect($out)->toContain('interface');
            expect($out)->toContain('CgTest2\Repo');
            expect($out)->toContain('[NEW FILE]');

            // Cleanup
            array_map('unlink', glob($absDir . '/src/CgTest2/*') ?: []);
            @rmdir($absDir . '/src/CgTest2');
            array_map('unlink', glob($absDir . '/spec/*'));
            @rmdir($absDir . '/src');
            @rmdir($absDir . '/spec');
            @rmdir($absDir);
        });
    });

    context('presents prompts through an injected chooser (pair mode)', function () {

        let('pairOutput', fn() => new PairOutput($this->output));

        it('generates the method when the chooser accepts', function () {
            $relDir = '.tmp_codegen_chooser_test_' . getmypid();
            $absDir = getcwd() . '/' . $relDir;
            @mkdir($absDir . '/src/CgChooser', 0777, true);
            @mkdir($absDir . '/spec/CgChooser', 0777, true);

            $classFile = $absDir . '/src/CgChooser/Widget.php';
            file_put_contents($classFile, "<?php\n\nnamespace CgChooser;\n\nclass Widget\n{\n}\n");

            $specFile = $absDir . '/spec/CgChooser/Widget.spec.php';
            file_put_contents($specFile, "<?php\nit('works', fn() => expect(\$this->widget->spin())->toBe(true));\n");

            $chooser = new Chooser($this->pairOutput, true, fn() => '1');
            $generator = new CodeGenerator($relDir . '/src', $relDir . '/spec', chooser: $chooser);

            $original = eval("return new \\Error('Call to undefined method CgChooser\\\\Widget::spin()');");
            $error = new \PhpSpec\Specification\ExampleError(
                'Call to undefined method CgChooser\Widget::spin()',
                $original,
            );
            $ref = new \ReflectionProperty(\Exception::class, 'file');
            $ref->setValue($error, $specFile);
            $ref = new \ReflectionProperty(\Exception::class, 'line');
            $ref->setValue($error, 2);

            $example = new ExampleResult('works', [], isError: true);
            $example->setError($error);
            $spec = new SpecificationResult('CgChooser\Widget', [$example]);
            $suite = new SuiteResult([$spec]);

            $generator->generate($this->output, $suite, false);
            $out = $this->output->fetch();

            expect($out)->not()->toContain('[Y/n]');
            expect($out)->toContain('1. Yes');
            expect($out)->toContain("2. Yes, and don't ask again — always create methods");
            expect($out)->toContain('3. No');
            expect($out)->toContain('[MODIFIED]');
            expect($out)->toContain('public function spin');

            // Cleanup
            array_map('unlink', glob($absDir . '/src/CgChooser/*'));
            array_map('unlink', glob($absDir . '/spec/CgChooser/*'));
            @rmdir($absDir . '/src/CgChooser');
            @rmdir($absDir . '/spec/CgChooser');
            @rmdir($absDir . '/src');
            @rmdir($absDir . '/spec');
            @rmdir($absDir);
        });

        it('skips generation when the chooser declines', function () {
            $relDir = '.tmp_codegen_chooser_decline_test_' . getmypid();
            $absDir = getcwd() . '/' . $relDir;
            @mkdir($absDir . '/src/CgChooserNo', 0777, true);
            @mkdir($absDir . '/spec/CgChooserNo', 0777, true);

            $classFile = $absDir . '/src/CgChooserNo/Widget.php';
            file_put_contents($classFile, "<?php\n\nnamespace CgChooserNo;\n\nclass Widget\n{\n}\n");

            $specFile = $absDir . '/spec/CgChooserNo/Widget.spec.php';
            file_put_contents($specFile, "<?php\nit('works', fn() => expect(\$this->widget->spin())->toBe(true));\n");

            $chooser = new Chooser($this->pairOutput, true, fn() => '3');
            $generator = new CodeGenerator($relDir . '/src', $relDir . '/spec', chooser: $chooser);

            $original = eval("return new \\Error('Call to undefined method CgChooserNo\\\\Widget::spin()');");
            $error = new \PhpSpec\Specification\ExampleError(
                'Call to undefined method CgChooserNo\Widget::spin()',
                $original,
            );
            $ref = new \ReflectionProperty(\Exception::class, 'file');
            $ref->setValue($error, $specFile);
            $ref = new \ReflectionProperty(\Exception::class, 'line');
            $ref->setValue($error, 2);

            $example = new ExampleResult('works', [], isError: true);
            $example->setError($error);
            $spec = new SpecificationResult('CgChooserNo\Widget', [$example]);
            $suite = new SuiteResult([$spec]);

            $generator->generate($this->output, $suite, false);

            expect(file_get_contents($classFile))->not()->toContain('function spin');

            // Cleanup
            array_map('unlink', glob($absDir . '/src/CgChooserNo/*'));
            array_map('unlink', glob($absDir . '/spec/CgChooserNo/*'));
            @rmdir($absDir . '/src/CgChooserNo');
            @rmdir($absDir . '/spec/CgChooserNo');
            @rmdir($absDir . '/src');
            @rmdir($absDir . '/spec');
            @rmdir($absDir);
        });
    });
});
