<?php

use PhpSpec\CodeGeneration\SourceLayout;
use PhpSpec\RealFilesystem;
use PhpSpec\Console\Prompt;
use PhpSpec\Console\Command\Run\GenerationCandidates;
use PhpSpec\Configuration;
use PhpSpec\CodeGeneration\StepsHome;
use PhpSpec\Console\Command\Pair\Chooser;
use PhpSpec\Console\Command\Pair\PairOutput;
use PhpSpec\Console\Command\Run\CodeGenerator;
use PhpSpec\Console\Command\Run\Generation;
use PhpSpec\Offers\Offer;
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\SuiteResult;
use Symfony\Component\Console\Output\BufferedOutput;

describe(CodeGenerator::class, function () {

    let('generator', fn() => new CodeGenerator(SourceLayout::under('src'), 'spec'));
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

            $generator = new CodeGenerator(SourceLayout::under($relDir . '/src'), $relDir . '/spec', Generation::Accepts);

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

        it('generates a static method with its arguments for a method called statically', function () {
            $relDir = '.tmp_codegen_static_' . getmypid();
            $absDir = getcwd() . '/' . $relDir;
            @mkdir($absDir . '/src/CgTest', 0777, true);
            @mkdir($absDir . '/spec/CgTest', 0777, true);

            $classFile = $absDir . '/src/CgTest/Widget.php';
            file_put_contents($classFile, "<?php\n\nnamespace CgTest;\n\nclass Widget\n{\n}\n");

            $specFile = $absDir . '/spec/CgTest/Widget.spec.php';
            file_put_contents($specFile, "<?php\nit('works', fn() => expect(Widget::of('a', 'b'))->toBeAnInstanceOf(Widget::class));\n");

            $generator = new CodeGenerator(SourceLayout::under($relDir . '/src'), $relDir . '/spec', Generation::Accepts);

            $original = eval("return new \\Error('Call to undefined method CgTest\\\\Widget::of()');");
            $error = new \PhpSpec\Specification\ExampleError('Call to undefined method CgTest\Widget::of()', $original);
            $ref = new \ReflectionProperty(\Exception::class, 'file');
            $ref->setValue($error, $specFile);
            $ref = new \ReflectionProperty(\Exception::class, 'line');
            $ref->setValue($error, 2);

            $example = new ExampleResult('works', [], isError: true);
            $example->setError($error);
            $suite = new SuiteResult([new SpecificationResult('CgTest\Widget', [$example])]);

            $generator->generate($this->output, $suite, false);

            expect(file_get_contents($classFile))->toContain('public static function of($argument1, $argument2)');

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

            $generator = new CodeGenerator(SourceLayout::under($relDir . '/src'), $relDir . '/spec', Generation::Accepts);

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
            expect($out)->toContain('[NEW FILE]');
            // Where it went, and under which namespace: taking the offer says
            // what was written, since there was no question to say it first.
            expect(file_get_contents($absDir . '/src/CgTest2/Repo.php'))->toContain('namespace CgTest2;');

            // Cleanup
            array_map('unlink', glob($absDir . '/src/CgTest2/*') ?: []);
            @rmdir($absDir . '/src/CgTest2');
            array_map('unlink', glob($absDir . '/spec/*'));
            @rmdir($absDir . '/src');
            @rmdir($absDir . '/spec');
            @rmdir($absDir);
        });
    });

    context('offers a class that does not exist yet where its error would have been reported', function () {

        it('says the spec is about a class that does not exist yet, then asks to generate it', function () {
            $generator = new CodeGenerator(SourceLayout::under('src'), 'spec', Generation::Declines);

            $error = new \PhpSpec\Specification\ExampleError('Class "App\Calculator" not found', new \Error('Class "App\Calculator" not found'));
            $example = new ExampleResult('App\Calculator', [], isError: true);
            $example->setError($error);
            $describe = new ContextResult('App\Calculator', [$example]);
            $describe->setError($error);
            $suite = new SuiteResult([new SpecificationResult('Calculator', [$describe])]);

            $generator->generate($this->output, $suite, false);

            expect($this->output->fetch())->toContain(implode(PHP_EOL, [
                '',
                '  Looks like you are trying to spec App\Calculator,',
                "  a class that doesn't exist yet.",
                '',
                '  Would you like me to generate that class for you?',
                '',
            ]));
        });

        it('reads a describe block titled by the short name as describing that class', function () {
            $generator = new CodeGenerator(SourceLayout::under('src'), 'spec', Generation::Declines);

            $error = new \PhpSpec\Specification\ExampleError('Class "App\Basket" not found', new \Error('Class "App\Basket" not found'));
            $example = new ExampleResult('totals nothing to start with', [], isError: true);
            $example->setError($error);
            $suite = new SuiteResult([new SpecificationResult('Basket', [new ContextResult('Basket', [$example])])]);

            $generator->generate($this->output, $suite, false);

            expect($this->output->fetch())->toContain('  Looks like you are trying to spec App\Basket,' . PHP_EOL);
        });

        it('names the class a spec needs when it is not the one it describes, and offers its spec before the class', function () {
            $generator = new CodeGenerator(SourceLayout::under('src'), 'spec', Generation::Declines);

            $error = new \PhpSpec\Specification\ExampleError('Class "App\Coupon" not found', new \Error('Class "App\Coupon" not found'));
            $example = new ExampleResult('applies a coupon', [], isError: true);
            $example->setError($error);
            $suite = new SuiteResult([new SpecificationResult('Basket', [new ContextResult('App\Basket', [$example])])]);

            $generator->generate($this->output, $suite, false);

            expect($this->output->fetch())->toContain(implode(PHP_EOL, [
                '  Looks like App\Basket needs App\Coupon,',
                "  a class that doesn't exist yet.",
                '',
                '  Do you want me to create a spec for it?',
                '',
            ]));
        });
    });

    context('reports what it applied', function () {

        it('returns each generated piece with its offer id, action, target and file', function () {
            $relDir = '.tmp_codegen_applied_' . getmypid();
            $absDir = getcwd() . '/' . $relDir;
            mkdir($absDir . '/src', 0777, true);
            mkdir($absDir . '/spec', 0777, true);

            $generator = new CodeGenerator(SourceLayout::under($relDir . '/src'), $relDir . '/spec', Generation::Accepts);
            $error = new \PhpSpec\Specification\ExampleError('Class "App\Coupon" not found', new \Error('Class "App\Coupon" not found'));
            $example = new ExampleResult('applies a coupon', [], isError: true);
            $example->setError($error);
            $suite = new SuiteResult([new SpecificationResult('Basket', [new ContextResult('App\Basket', [$example])])]);

            try {
                $applied = $generator->generate($this->output, $suite, false);
            } finally {
                foreach ([$absDir . '/src/App/Coupon.php', $absDir . '/spec/App/Coupon.spec.php'] as $file) {
                    is_file($file) && unlink($file);
                }
                foreach ([$absDir . '/src/App', $absDir . '/src', $absDir . '/spec/App', $absDir . '/spec', $absDir] as $dir) {
                    is_dir($dir) && rmdir($dir);
                }
            }

            expect($applied)->toBe([[
                'id' => Offer::generate('create_spec', 'App\Coupon', [])->id,
                'action' => 'create_spec',
                'target' => 'App\Coupon',
                'file' => $relDir . '/spec/App/Coupon.spec.php',
                'applied' => true,
            ], [
                'id' => Offer::generate('create_class', 'App\Coupon', [])->id,
                'action' => 'create_class',
                'target' => 'App\Coupon',
                'file' => $relDir . '/src/App/Coupon.php',
                'applied' => true,
            ]]);
        });

        it('reports a method it could not write as not applied, with the reason', function () {
            $relDir = '.tmp_codegen_refused_' . getmypid();
            $absDir = getcwd() . '/' . $relDir;
            mkdir($absDir . '/src/CgTest', 0777, true);
            mkdir($absDir . '/spec/CgTest', 0777, true);
            file_put_contents($absDir . '/src/CgTest/Widget.php', "<?php\n\nnamespace CgTest;\n\nclass Widget\n{\n    public function spin() {}\n}\n");
            $specFile = $absDir . '/spec/CgTest/Widget.spec.php';
            file_put_contents($specFile, "<?php\nit('works', fn() => expect(\$this->widget->spin())->toBe(true));\n");

            $generator = new CodeGenerator(SourceLayout::under($relDir . '/src'), $relDir . '/spec', Generation::Accepts);
            $original = eval("return new \\Error('Call to undefined method CgTest\\\\Widget::spin()');");
            $error = new \PhpSpec\Specification\ExampleError('Call to undefined method CgTest\Widget::spin()', $original);
            foreach (['file' => $specFile, 'line' => 2] as $property => $value) {
                (new \ReflectionProperty(\Exception::class, $property))->setValue($error, $value);
            }
            $example = new ExampleResult('works', [], isError: true);
            $example->setError($error);
            $suite = new SuiteResult([new SpecificationResult('CgTest\Widget', [$example])]);

            try {
                $applied = $generator->generate($this->output, $suite, false);
            } finally {
                array_map('unlink', array_merge(glob($absDir . '/src/CgTest/*') ?: [], glob($absDir . '/spec/CgTest/*') ?: []));
                foreach ([$absDir . '/src/CgTest', $absDir . '/spec/CgTest', $absDir . '/src', $absDir . '/spec', $absDir] as $dir) {
                    rmdir($dir);
                }
            }

            expect($applied)->toHaveLength(1);
            expect($applied[0]['action'])->toBe('create_method');
            expect($applied[0]['target'])->toBe('CgTest\Widget::spin');
            expect($applied[0]['applied'])->toBeFalse();
            expect($applied[0]['reason'])->toContain('already exists');
        });

        it('returns nothing when nothing was written', function () {
            $generator = new CodeGenerator(SourceLayout::under('src'), 'spec', Generation::Declines);
            $error = new \PhpSpec\Specification\ExampleError('Class "App\Coupon" not found', new \Error('Class "App\Coupon" not found'));
            $example = new ExampleResult('applies a coupon', [], isError: true);
            $example->setError($error);
            $suite = new SuiteResult([new SpecificationResult('Basket', [new ContextResult('App\Basket', [$example])])]);

            expect($generator->generate($this->output, $suite, false))->toBe([]);
        });
    });

    context('does not offer to create a class whose file already exists', function () {

        it('skips the create-class offer for a "Class not found" that is really a PSR-4/autoload mismatch', function () {
            // The bug: a run reports `Class "App\Model\User" not found` — a runtime
            // autoload failure (no/mismatched PSR-4 mapping), not a missing file — and
            // the generator, resolving the guard path with a *different* prefix than it
            // would write with, offered to create a class that is already on disk.
            $relDir = '.tmp_codegen_existing_' . getmypid();
            $absDir = getcwd() . '/' . $relDir;
            @mkdir($absDir . '/src/Model', 0777, true);
            @mkdir($absDir . '/spec', 0777, true);

            // File already present at the PSR-4 path (prefix "App" stripped -> src/Model/User.php)
            file_put_contents(
                $absDir . '/src/Model/User.php',
                "<?php\n\nnamespace App\\Model;\n\nclass User\n{\n}\n",
            );

            $generator = new CodeGenerator(SourceLayout::under($relDir . '/src', 'App'), $relDir . '/spec', Generation::Accepts);

            $original = new \RuntimeException('Class "App\Model\User" not found');
            $error = new \PhpSpec\Specification\ExampleError('Class "App\Model\User" not found', $original);
            $example = new ExampleResult('is a user', [], isError: true);
            $example->setError($error);
            $spec = new SpecificationResult('App\Model\User', [$example]);
            $suite = new SuiteResult([$spec]);

            $generator->generate($this->output, $suite, false);
            $out = $this->output->fetch();

            expect($out)->not()->toContain('Would you like me to generate');
            expect($out)->toContain($relDir . '/src/Model/User.php exists, but App\Model\User could not be autoloaded: check the PSR-4 mapping in composer.json.');

            // The existing file is untouched.
            expect(file_get_contents($absDir . '/src/Model/User.php'))->toContain('class User');

            // Cleanup
            @unlink($absDir . '/src/Model/User.php');
            @rmdir($absDir . '/src/Model');
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
            $generator = new CodeGenerator(SourceLayout::under($relDir . '/src'), $relDir . '/spec', chooser: $chooser);

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
            $generator = new CodeGenerator(SourceLayout::under($relDir . '/src'), $relDir . '/spec', chooser: $chooser);

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
    context('undefined steps', function () {
        let('root', function () {
            $root = sys_get_temp_dir() . '/phpspec_codegen_steps_' . uniqid();
            mkdir($root . '/features', 0777, true);

            return $root;
        });
        afterEach(function () {
            $remove = function (string $dir) use (&$remove): void {
                foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $entry) {
                    is_dir("$dir/$entry") ? $remove("$dir/$entry") : unlink("$dir/$entry");
                }
                rmdir($dir);
            };
            $remove($this->root);
        });
        let('candidates', fn() => new GenerationCandidates(
            undefinedSteps: [
                ['keyword' => 'Given', 'text' => 'a user named "Chuck Norris"'],
                ['keyword' => 'When', 'text' => 'the user plays'],
                ['keyword' => 'When', 'text' => 'the user plays'],
            ],
            stepsFile: 'features/steps/steps.php',
        ));
        let('stepsFiles', function () {
            mkdir($this->root . '/features/steps');
            file_put_contents($this->root . '/features/steps/web.steps.php', "<?php\n\ngiven(\"I visit {string}\", function (string \$arg1) {\n    pending();\n});\n");
            file_put_contents($this->root . '/features/steps/assertions.steps.php', "<?php\n\nthen(\"nothing else happens\", function () {\n    pending();\n});\n");

            return ['web' => $this->root . '/features/steps/web.steps.php', 'assertions' => $this->root . '/features/steps/assertions.steps.php'];
        });
        let('answering', fn() => function (Prompt $prompt, string ...$answers): Prompt {
            allow($prompt->ask())->toReturnUsing(function () use (&$answers): ?string {
                return array_shift($answers);
            });

            return $prompt;
        });
        let('steps', fn() => new StepsHome(new Configuration(), new RealFilesystem(), $this->root));

        it('asks once, Y/n, when there is no steps file, and writes every step to steps.php once', function (Prompt $prompt) {
            $generator = new CodeGenerator(SourceLayout::under('src'), 'spec', steps: $this->steps, prompt: ($this->answering)($prompt, ''));

            $applied = $generator->apply($this->output, $this->candidates, false);

            $out = $this->output->fetch();
            expect(substr_count($out, 'You have undefined steps. Would you like me to generate the steps for you? [Y/n]'))->toBe(1);
            $written = (string) file_get_contents($this->root . '/features/steps/steps.php');
            expect($written)->toContain('given("a user named {string}", function (string $arg1) {');
            expect(substr_count($written, 'the user plays'))->toBe(1);
            expect($applied)->toBe([[
                'id' => Offer::generate('create_steps', 'features/steps/steps.php', [])->id,
                'action' => 'create_steps',
                'target' => 'features/steps/steps.php',
                'file' => 'features/steps/steps.php',
                'applied' => true,
            ]]);
        });

        it('offers the steps files there are and writes to the one picked', function (Prompt $prompt) {
            $files = $this->stepsFiles;
            $generator = new CodeGenerator(SourceLayout::under('src'), 'spec', steps: $this->steps, prompt: ($this->answering)($prompt, '2'));

            $generator->apply($this->output, $this->candidates, false);

            expect(str_replace("\r\n", "\n", $this->output->fetch()))->toContain(implode("\n", [
                'You have undefined steps. Would you like me to generate the steps for you?',
                '',
                '  [0] No, skip',
                '  [1] assertions.steps.php',
                '  [2] web.steps.php',
                '  [3] New file...',
            ]));
            expect((string) file_get_contents($files['web']))->toContain('a user named {string}');
            expect((string) file_get_contents($files['assertions']))->not()->toContain('a user named');
            expect(file_exists($this->root . '/features/steps/steps.php'))->toBeFalse();
        });

        it('asks again on an answer that is no option, and takes the first file on Enter', function (Prompt $prompt) {
            $files = $this->stepsFiles;
            $generator = new CodeGenerator(SourceLayout::under('src'), 'spec', steps: $this->steps, prompt: ($this->answering)($prompt, '9', 'y', ''));

            $generator->apply($this->output, $this->candidates, false);

            expect(substr_count($this->output->fetch(), 'Answer with a number from 0 to 3.'))->toBe(2);
            expect((string) file_get_contents($files['assertions']))->toContain('a user named {string}');
        });

        it('writes to a new steps file the person names, refusing a path for a name', function (Prompt $prompt) {
            $this->stepsFiles;
            $generator = new CodeGenerator(SourceLayout::under('src'), 'spec', steps: $this->steps, prompt: ($this->answering)($prompt, '3', 'a/b', 'players'));

            $applied = $generator->apply($this->output, $this->candidates, false);

            $out = $this->output->fetch();
            expect($out)->toContain('Name the new steps file');
            expect($out)->toContain('A steps file is named, not placed: for example web.');
            expect((string) file_get_contents($this->root . '/features/steps/players.steps.php'))->toContain('a user named {string}');
            expect($applied[0]['file'])->toBe('features/steps/players.steps.php');
        });

        it('writes nothing when the person skips', function (Prompt $prompt) {
            $files = $this->stepsFiles;
            $generator = new CodeGenerator(SourceLayout::under('src'), 'spec', steps: $this->steps, prompt: ($this->answering)($prompt, '0'));

            expect($generator->apply($this->output, $this->candidates, false))->toBe([]);
            expect((string) file_get_contents($files['web']))->not()->toContain('a user named');
        });

        it('never writes a step another steps file already defines', function (Prompt $prompt) {
            $files = $this->stepsFiles;
            file_put_contents($files['assertions'], "<?php\n\nwhen(\"the user plays\", function () {\n    pending();\n});\n");
            $generator = new CodeGenerator(SourceLayout::under('src'), 'spec', steps: $this->steps, prompt: ($this->answering)($prompt, '2'));

            $generator->apply($this->output, $this->candidates, false);

            expect((string) file_get_contents($files['web']))->toContain('a user named {string}');
            expect((string) file_get_contents($files['web']))->not()->toContain('the user plays');
        });

        it('with nobody to answer, shows no options, writes nothing and names where the steps would go', function () {
            $this->stepsFiles;
            $generator = new CodeGenerator(SourceLayout::under('src'), 'spec', Generation::Declines, steps: $this->steps);

            expect($generator->apply($this->output, $this->candidates, false))->toBe([]);

            $out = $this->output->fetch();
            expect($out)->toContain('You have undefined steps. Would you like me to generate the steps for you?');
            expect($out)->toContain('Nothing was written: there is nobody to answer. Run with --accept-offers to append them to features/steps/steps.php.');
            expect($out)->not()->toContain('[0] No, skip');
            expect(file_exists($this->root . '/features/steps/steps.php'))->toBeFalse();
        });

        it('appends to steps.php when the offers are accepted, whatever other steps files there are', function () {
            $files = $this->stepsFiles;
            $generator = new CodeGenerator(SourceLayout::under('src'), 'spec', Generation::Accepts, steps: $this->steps);

            $generator->apply($this->output, $this->candidates, false);

            expect((string) file_get_contents($this->root . '/features/steps/steps.php'))->toContain('a user named {string}');
            expect((string) file_get_contents($files['web']))->not()->toContain('a user named');
        });

        it('puts the steps to the pair chooser as yes or no for steps.php, with no picker', function () {
            $this->stepsFiles;
            $chooser = new Chooser(new PairOutput($this->output), true, fn() => '1');
            $generator = new CodeGenerator(SourceLayout::under('src'), 'spec', chooser: $chooser, steps: $this->steps);

            $generator->apply($this->output, $this->candidates, false);

            $out = $this->output->fetch();
            expect($out)->toContain("2. Yes, and don't ask again — always append them to features/steps/steps.php");
            expect($out)->not()->toContain('[0] No, skip');
            expect((string) file_get_contents($this->root . '/features/steps/steps.php'))->toContain('a user named {string}');
        });
    });
});

