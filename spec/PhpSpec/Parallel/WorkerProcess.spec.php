<?php

use PhpSpec\Parallel\Wire;
use PhpSpec\Parallel\WorkerProcess;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\StepResult;
use PhpSpec\StopConditions;

describe(WorkerProcess::class, function () {

    context('getResults with no output', function () {
        it('returns empty array when stdout is empty', function () {
            $worker = new WorkerProcess([], '/dev/null');
            $results = $worker->getResults();

            expect($results)->toHaveCount(0);
        });
    });

    context('reading results off the wire', function () {
        $wire = new Wire();
        $failing = new SpecificationResult('Basket', [
            new ExampleResult('fails', [MatchResult::failed(1, 2, 'Expected 1 to be 2', '/project/spec/App/Basket.spec.php', 5, null, 'toBe')]),
        ], 'spec/App/Basket.spec.php');
        $feature = new FeatureResult('Checkout', [new ScenarioResult('Paying', [new StepResult('Given a basket', 'passed')], 3)], 'features/checkout.feature');

        it('turns each line on the wire into the result it carries, the whole of it', function () use ($wire, $failing, $feature) {
            $worker = new WorkerProcess(['spec/App/Basket.spec.php', 'features/checkout.feature'], '/dev/null');
            $ref = new \ReflectionProperty($worker, 'stdout');
            $ref->setValue($worker, $wire->encode($failing) . "\n" . $wire->encode($feature) . "\n" . $wire->ending() . "\n");

            $results = $worker->getResults();

            expect($results)->toHaveCount(2);
            expect($results[0])->toBeAnInstanceOf(SpecificationResult::class);
            $match = $results[0]->getResults()[0]->getResults()[0];
            expect($match->getExpected())->toBe(1);
            expect($match->getActual())->toBe(2);
            expect($match->getMatcher())->toBe('toBe');
            expect($match->getFile())->toBe('/project/spec/App/Basket.spec.php');
            expect($results[1])->toBeAnInstanceOf(FeatureResult::class);
            expect($results[1]->getResults()[0]->getLine())->toBe(3);
        });

        it('passes over a line that is not on the wire, such as what a bootstrap file printed', function () use ($wire, $failing) {
            $worker = new WorkerProcess(['spec/App/Basket.spec.php'], '/dev/null');
            $ref = new \ReflectionProperty($worker, 'stdout');
            $ref->setValue($worker, "Bootstrap loaded...\n" . $wire->encode($failing) . "\n{\"not\": \"the wire\"}\n" . $wire->ending() . "\n");

            expect($worker->getResults())->toHaveCount(1);
        });

        it('hands over the results whose lines have fully arrived, and keeps a line still arriving for the next read', function () use ($wire, $failing, $feature) {
            $worker = new WorkerProcess(['spec/App/Basket.spec.php', 'features/checkout.feature'], '/dev/null');
            $ref = new \ReflectionProperty($worker, 'stdout');
            $second = $wire->encode($feature);
            $ref->setValue($worker, $wire->encode($failing) . "\n" . substr($second, 0, 20));

            expect($worker->takeResults())->toHaveCount(1);
            expect($worker->takeResults())->toHaveCount(0);

            $ref->setValue($worker, $ref->getValue($worker) . substr($second, 20) . "\n");

            expect($worker->takeResults())->toHaveCount(1);
        });

        it('reports a file the worker died before reporting as an error on that file, naming the exit code and what the worker said', function () use ($wire, $failing) {
            $worker = new WorkerProcess(['spec/App/Basket.spec.php', 'spec/App/Coupon.spec.php'], '/dev/null');
            (new \ReflectionProperty($worker, 'stdout'))->setValue($worker, $wire->encode($failing) . "\n");
            (new \ReflectionProperty($worker, 'stderr'))->setValue($worker, "PHP Fatal error:  Allowed memory size exhausted\n");
            (new \ReflectionProperty($worker, 'exitCode'))->setValue($worker, 255);

            $results = $worker->getResults();

            expect($results)->toHaveCount(2);
            expect($results[1]->getTitle())->toBe('Coupon.spec.php');
            $example = $results[1]->getResults()[0];
            expect($example->isError())->toBeTrue();
            expect($example->getError()->getMessage())->toContain('exited with code 255 before reporting spec/App/Coupon.spec.php');
            expect($example->getError()->getMessage())->toContain('Allowed memory size exhausted');
        });

        it('reports nothing missing when the worker ended its report, whatever its exit code', function () use ($wire, $failing) {
            $worker = new WorkerProcess(['spec/App/Basket.spec.php'], '/dev/null');
            (new \ReflectionProperty($worker, 'stdout'))->setValue($worker, $wire->encode($failing) . "\n" . $wire->ending() . "\n");
            (new \ReflectionProperty($worker, 'exitCode'))->setValue($worker, 1);

            expect($worker->getResults())->toHaveCount(1);
        });
    });

    context('coverage', function () {
        it('loses nothing when its coverage arrived, or when none was asked for', function () {
            $partial = (string) tempnam(sys_get_temp_dir(), 'phpspec_partial_');

            try {
                expect((new WorkerProcess(['spec/A.spec.php'], '/dev/null', $partial))->lostCoverage())->toBeNull();
                expect((new WorkerProcess(['spec/A.spec.php'], '/dev/null'))->lostCoverage())->toBeNull();
            } finally {
                unlink($partial);
            }
        });

        it('says it exited before handing over its coverage, with what it said', function () {
            $worker = new WorkerProcess(['spec/A.spec.php'], '/dev/null', sys_get_temp_dir() . '/phpspec_never_' . uniqid() . '.json');
            (new \ReflectionProperty($worker, 'stderr'))->setValue($worker, "PHP Fatal error:  Allowed memory size exhausted\n");
            (new \ReflectionProperty($worker, 'exitCode'))->setValue($worker, 255);

            expect($worker->lostCoverage())->toBe("Coverage is incomplete: a worker exited with code 255 before handing over its coverage.\nPHP Fatal error:  Allowed memory size exhausted");
        });

        it('says it was stopped with the run before handing over its coverage', function () {
            $worker = new WorkerProcess(['spec/A.spec.php'], '/dev/null', sys_get_temp_dir() . '/phpspec_never_' . uniqid() . '.json');

            $worker->terminate();

            expect($worker->lostCoverage())->toBe('Coverage is incomplete: a worker was stopped with the run before handing over its coverage.');
        });
    });

    context('buildCommand', function () {
        it('runs under the php options it is given, before the binary, and asks for the wire format', function () {
            $worker = new WorkerProcess(['spec/A.spec.php'], '/path/to/phpspec', phpOptions: ['-d', 'pcov.enabled=0', '-d', 'xdebug.mode=off']);
            $command = $worker->buildCommand();

            expect(array_slice($command, 1, 5))->toBe(['-d', 'pcov.enabled=0', '-d', 'xdebug.mode=off', '/path/to/phpspec']);
            expect($command)->toContain('spec/A.spec.php');
            expect($command)->toContain('wire');
            expect($command)->not()->toContain('junit');
        });

        it('requests a partial dump when a coverage partial path is set', function () {
            $worker = new WorkerProcess(['spec/A.spec.php'], '/path/to/phpspec', '/tmp/partial-0.json');
            $command = $worker->buildCommand();

            expect($command)->toContain('--coverage-partial=/tmp/partial-0.json');
        });

        it('forwards the options that set up the project, a named config and a bootstrap, so a worker runs against the same one', function () {
            $command = (new WorkerProcess(['spec/A.spec.php'], '/path/to/phpspec', childOptions: ['--config=custom/my-config.json', '--bootstrap=custom/boot.php']))->buildCommand();

            expect($command)->toContain('--config=custom/my-config.json');
            expect($command)->toContain('--bootstrap=custom/boot.php');
        });

        it('forwards the title filter and the tag expression, so a worker selects as the parent did', function () {
            $command = (new WorkerProcess(['spec/A.spec.php'], '/path/to/phpspec', filter: 'adds', tags: '@smoke and not @wip'))->buildCommand();

            expect($command)->toContain('--filter=adds');
            expect($command)->toContain('--tags=@smoke and not @wip');
            expect((new WorkerProcess(['spec/A.spec.php'], '/path/to/phpspec'))->buildCommand())->not()->toContain('--filter=');
        });

        it('forwards the stop conditions to the worker, so it halts as the parent would', function () {
            $worker = new WorkerProcess(['spec/A.spec.php'], '/path/to/phpspec', stop: new StopConditions(onError: true, onSkipped: true));
            $command = $worker->buildCommand();

            expect($command)->toContain('--stop-on-error');
            expect($command)->toContain('--stop-on-skipped');
            expect($command)->not()->toContain('--stop-on-failure');
        });
    });

    context('getExitCode', function () {
        it('returns null before process runs', function () {
            $worker = new WorkerProcess([], '/dev/null');
            expect($worker->getExitCode())->toBeNull();
        });
    });

    context('getStderr', function () {
        it('returns empty string initially', function () {
            $worker = new WorkerProcess([], '/dev/null');
            expect($worker->getStderr())->toBe('');
        });
    });
});
