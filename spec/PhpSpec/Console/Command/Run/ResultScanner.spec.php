<?php

use PhpSpec\Console\Command\Run\ResultScanner;
use PhpSpec\Console\Command\Run\SourceAnalyser;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ContextResult;
use PhpSpec\Specification\ExampleError;
use PhpSpec\StoryBDD\StepError;
use PhpSpec\Mock\Double;

interface ResultScannerTestInterface {
    public function doWork(): void;
}

describe(ResultScanner::class, function () {

    let('scanner', fn() => new ResultScanner(new SourceAnalyser()));

    context('collectMissingMockTypes', function () {

        it('collects missing mock type from error result', function () {
            $errorMsg = "Cannot create mock: class or interface 'App\\Service\\Mailer' does not exist. Make sure the class is autoloaded or the file is included.";
            $error = new ExampleError($errorMsg, new \RuntimeException($errorMsg));

            $example = new ExampleResult('test', [], isError: true);
            $example->setError($error);
            $spec = new SpecificationResult("spec", [$example]);
            $suite = new SuiteResult([$spec]);

            $result = $this->scanner->collectMissingMockTypes($suite);
            expect($result)->toHaveCount(1);
            expect($result[0])->toBe('App\\Service\\Mailer');
        });

        it('returns empty array when no mock errors', function () {
            $example = new ExampleResult('test', []);
            $spec = new SpecificationResult("spec", [$example]);
            $suite = new SuiteResult([$spec]);

            $result = $this->scanner->collectMissingMockTypes($suite);
            expect($result)->toHaveCount(0);
        });

        it('recurses into nested context results', function () {
            $errorMsg = "Cannot create mock: class or interface 'App\\Foo' does not exist. Make sure the class is autoloaded or the file is included.";
            $error = new ExampleError($errorMsg, new \RuntimeException($errorMsg));

            $example = new ExampleResult('test', [], isError: true);
            $example->setError($error);
            $ctx = new ContextResult('ctx', [$example]);
            $spec = new SpecificationResult("spec", [$ctx]);
            $suite = new SuiteResult([$spec]);

            $result = $this->scanner->collectMissingMockTypes($suite);
            expect($result)->toHaveCount(1);
        });

        it('ignores non-mock errors', function () {
            $error = new ExampleError("some other error", new \RuntimeException("some other error"));

            $example = new ExampleResult('test', [], isError: true);
            $example->setError($error);
            $spec = new SpecificationResult("spec", [$example]);
            $suite = new SuiteResult([$spec]);

            $result = $this->scanner->collectMissingMockTypes($suite);
            expect($result)->toHaveCount(0);
        });
    });

    context('collectUndefinedClassMethods', function () {

        it('collects undefined class method errors', function () {
            $error = new ExampleError(
                "Call to undefined method App\\Calculator::add()",
                new \RuntimeException("Call to undefined method App\\Calculator::add()")
            );

            $example = new ExampleResult('test', [], isError: true);
            $example->setError($error);
            $spec = new SpecificationResult("spec", [$example]);
            $suite = new SuiteResult([$spec]);

            $result = $this->scanner->collectUndefinedClassMethods($suite);
            expect($result)->toHaveCount(1);
            expect($result[0]['className'])->toBe('App\\Calculator');
            expect($result[0]['methodName'])->toBe('add');
        });

        it('skips mock double errors', function () {
            $error = new ExampleError(
                "Call to undefined method MyClass_Double_abc123::someMethod()",
                new \RuntimeException("Call to undefined method MyClass_Double_abc123::someMethod()")
            );

            $example = new ExampleResult('test', [], isError: true);
            $example->setError($error);
            $spec = new SpecificationResult("spec", [$example]);
            $suite = new SuiteResult([$spec]);

            $result = $this->scanner->collectUndefinedClassMethods($suite);
            expect($result)->toHaveCount(0);
        });

        it('recurses into nested results', function () {
            $error = new ExampleError(
                "Call to undefined method Foo::bar()",
                new \RuntimeException("Call to undefined method Foo::bar()")
            );

            $example = new ExampleResult('test', [], isError: true);
            $example->setError($error);
            $ctx = new ContextResult('ctx', [$example]);
            $spec = new SpecificationResult("spec", [$ctx]);
            $suite = new SuiteResult([$spec]);

            $result = $this->scanner->collectUndefinedClassMethods($suite);
            expect($result)->toHaveCount(1);
        });
    });

    context('collectUndefinedMockInterfaceMethods', function () {

        it('collects undefined method on mock interface', function () {
            // Create a real mock to get a registered double
            $mock = mock(ResultScannerTestInterface::class);
            $mockClassName = get_class($mock);

            $error = new ExampleError(
                "Call to undefined method {$mockClassName}::newMethod()",
                new \RuntimeException("Call to undefined method {$mockClassName}::newMethod()")
            );

            $example = new ExampleResult('test', [], isError: true);
            $example->setError($error);
            $spec = new SpecificationResult("spec", [$example]);
            $suite = new SuiteResult([$spec]);

            $result = $this->scanner->collectUndefinedMockInterfaceMethods($suite);
            expect($result)->toHaveCount(1);
            expect($result[0]['methodName'])->toBe('newMethod');
        });

        it('skips non-double class method errors', function () {
            $error = new ExampleError(
                "Call to undefined method SomeRegularClass::newMethod()",
                new \RuntimeException("Call to undefined method SomeRegularClass::newMethod()")
            );

            $example = new ExampleResult('test', [], isError: true);
            $example->setError($error);
            $spec = new SpecificationResult("spec", [$example]);
            $suite = new SuiteResult([$spec]);

            $result = $this->scanner->collectUndefinedMockInterfaceMethods($suite);
            expect($result)->toHaveCount(0);
        });

        it('recurses into nested results', function () {
            $example = new ExampleResult('test', []);
            $ctx = new ContextResult('ctx', [$example]);
            $spec = new SpecificationResult("spec", [$ctx]);
            $suite = new SuiteResult([$spec]);

            $result = $this->scanner->collectUndefinedMockInterfaceMethods($suite);
            expect($result)->toHaveCount(0);
        });

        it('handles ReflectionException for non-existent registered class', function () {
            // Register a fake entry in the double registry
            $fakeMockName = 'Fake_Double_' . uniqid();
            Double::$doubleRegistry[$fakeMockName] = 'NonExistentClass' . uniqid();

            $error = new ExampleError(
                "Call to undefined method {$fakeMockName}::someMethod()",
                new \RuntimeException("Call to undefined method {$fakeMockName}::someMethod()")
            );

            $example = new ExampleResult('test', [], isError: true);
            $example->setError($error);
            $spec = new SpecificationResult("spec", [$example]);
            $suite = new SuiteResult([$spec]);

            $result = $this->scanner->collectUndefinedMockInterfaceMethods($suite);
            expect($result)->toHaveCount(0);
        });
    });

    context('collectUndefinedSteps', function () {

        it('collects undefined steps from feature results', function () {
            $steps = [
                new StepResult("Given a defined step", "passed"),
                new StepResult("When an undefined step", "undefined"),
                new StepResult("Then another undefined", "undefined"),
            ];
            $scenario = new ScenarioResult("Test", $steps);
            $feature = new FeatureResult("Feature", [$scenario], '/features/test.feature');
            $suite = new SuiteResult([$feature]);

            $result = $this->scanner->collectUndefinedSteps($suite);
            expect($result)->toHaveKey('/features/test.feature');
            expect($result['/features/test.feature'])->toHaveCount(2);
            expect($result['/features/test.feature'][0]['keyword'])->toBe('When');
            expect($result['/features/test.feature'][0]['text'])->toBe('an undefined step');
        });

        it('returns empty array when no undefined steps', function () {
            $steps = [new StepResult("Given passed", "passed")];
            $scenario = new ScenarioResult("Test", $steps);
            $feature = new FeatureResult("Feature", [$scenario], '/features/test.feature');
            $suite = new SuiteResult([$feature]);

            $result = $this->scanner->collectUndefinedSteps($suite);
            expect($result)->toBe([]);
        });
    });

    context('collectMissingStepClasses', function () {

        it('collects missing class names from step errors', function () {
            $step = new StepResult("Given step", "failure");
            $step->setError(new StepError('Class "App\\Service\\Mailer" not found', new \RuntimeException('Class "App\\Service\\Mailer" not found')));

            $scenario = new ScenarioResult("Test", [$step]);
            $feature = new FeatureResult("Feature", [$scenario]);
            $suite = new SuiteResult([$feature]);

            $result = $this->scanner->collectMissingStepClasses($suite);
            expect($result)->toHaveCount(1);
            expect($result[0])->toBe('App\\Service\\Mailer');
        });

        it('returns empty when no class not found errors', function () {
            $step = new StepResult("Given step", "failure");
            $step->setError(new StepError('Some other error', new \RuntimeException('Some other error')));

            $scenario = new ScenarioResult("Test", [$step]);
            $feature = new FeatureResult("Feature", [$scenario]);
            $suite = new SuiteResult([$feature]);

            $result = $this->scanner->collectMissingStepClasses($suite);
            expect($result)->toBe([]);
        });
    });

    context('collectMissingSpecClasses', function () {

        it('collects missing class names from spec example errors', function () {
            $error = new ExampleError(
                'Class "App\\Service\\Mailer" not found',
                new \RuntimeException('Class "App\\Service\\Mailer" not found')
            );

            $example = new ExampleResult('test', [], isError: true);
            $example->setError($error);
            $spec = new SpecificationResult("spec", [$example]);
            $suite = new SuiteResult([$spec]);

            $result = $this->scanner->collectMissingSpecClasses($suite);
            expect($result)->toHaveCount(1);
            expect($result[0])->toBe('App\\Service\\Mailer');
        });

        it('deduplicates missing classes', function () {
            $error = new ExampleError(
                'Class "App\\Foo" not found',
                new \RuntimeException('Class "App\\Foo" not found')
            );

            $ex1 = new ExampleResult('test1', [], isError: true);
            $ex1->setError($error);
            $ex2 = new ExampleResult('test2', [], isError: true);
            $ex2->setError($error);
            $spec = new SpecificationResult("spec", [$ex1, $ex2]);
            $suite = new SuiteResult([$spec]);

            $result = $this->scanner->collectMissingSpecClasses($suite);
            expect($result)->toHaveCount(1);
        });

        it('returns empty when no class-not-found errors', function () {
            $example = new ExampleResult('test', []);
            $spec = new SpecificationResult("spec", [$example]);
            $suite = new SuiteResult([$spec]);

            $result = $this->scanner->collectMissingSpecClasses($suite);
            expect($result)->toBe([]);
        });

        it('recurses into nested results', function () {
            $error = new ExampleError(
                'Class "App\\Bar" not found',
                new \RuntimeException('Class "App\\Bar" not found')
            );

            $example = new ExampleResult('test', [], isError: true);
            $example->setError($error);
            $ctx = new ContextResult('ctx', [$example]);
            $spec = new SpecificationResult("spec", [$ctx]);
            $suite = new SuiteResult([$spec]);

            $result = $this->scanner->collectMissingSpecClasses($suite);
            expect($result)->toHaveCount(1);
        });
    });

    context('collectFakeableMethods', function () {

        it('collects fakeable methods from failed results with fakeExpression', function () {
            // Create a temp file that has the right source pattern
            $tmpFile = tempnam(sys_get_temp_dir(), 'phpspec_test_');
            file_put_contents($tmpFile, implode("\n", [
                '<?php',
                '$calc = new App\\Calculator();',
                'expect($calc->add(1, 2))->toBe(3);',
            ]));

            $match = MatchResult::failed(3, null, 'Expected 3', $tmpFile, 3, 'return 3');
            $example = new ExampleResult('test', [$match]);
            $spec = new SpecificationResult("spec", [$example]);
            $suite = new SuiteResult([$spec]);

            $result = $this->scanner->collectFakeableMethods($suite);
            unlink($tmpFile);
            expect($result)->toHaveCount(1);
            expect($result[0]['className'])->toBe('App\\Calculator');
            expect($result[0]['methodName'])->toBe('add');
            expect($result[0]['fakeExpression'])->toBe('return 3');
        });

        it('collects fakeable methods from $this->prop->method() pattern', function () {
            $tmpFile = tempnam(sys_get_temp_dir(), 'phpspec_test_');
            file_put_contents($tmpFile, implode("\n", [
                '<?php',
                'use App\\Calculator;',
                'let("calc", fn() => new Calculator());',
                'expect($this->calc->add(1, 2))->toBe(3);',
            ]));

            $match = MatchResult::failed(3, null, 'Expected 3', $tmpFile, 4, 'return 3');
            $example = new ExampleResult('test', [$match]);
            $spec = new SpecificationResult("spec", [$example]);
            $suite = new SuiteResult([$spec]);

            $result = $this->scanner->collectFakeableMethods($suite);
            unlink($tmpFile);
            expect($result)->toHaveCount(1);
            expect($result[0]['className'])->toBe('App\\Calculator');
            expect($result[0]['methodName'])->toBe('add');
        });

        it('skips passed match results', function () {
            $match = MatchResult::failed(null, null, 'Failed', '/tmp/fake.php', 1);
            $example = new ExampleResult('test', [$match]);
            $spec = new SpecificationResult("spec", [$example]);
            $suite = new SuiteResult([$spec]);

            $result = $this->scanner->collectFakeableMethods($suite);
            expect($result)->toHaveCount(0);
        });

        it('recurses into nested results', function () {
            $example = new ExampleResult('test', []);
            $ctx = new ContextResult('ctx', [$example]);
            $spec = new SpecificationResult("spec", [$ctx]);
            $suite = new SuiteResult([$spec]);

            $result = $this->scanner->collectFakeableMethods($suite);
            expect($result)->toHaveCount(0);
        });

        it('skips when file does not exist', function () {
            $match = MatchResult::failed(42, null, 'Expected 42', '/nonexistent/file.php', 1, 'return 42');
            $example = new ExampleResult('test', [$match]);
            $spec = new SpecificationResult("spec", [$example]);
            $suite = new SuiteResult([$spec]);

            $result = $this->scanner->collectFakeableMethods($suite);
            expect($result)->toHaveCount(0);
        });
    });

});
