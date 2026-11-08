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

    context('listening to a worker', function () {
        it('stops listening to a pipe the worker closed, so a wait is never woken by it again', function () {
            $script = tempnam(sys_get_temp_dir(), 'phpspec_worker_spec_');
            file_put_contents($script, '<?php echo "done\n";');
            $worker = new WorkerProcess($script);
            $worker->start();

            $deadline = microtime(true) + 5;
            while ($worker->pipes() !== [] && microtime(true) < $deadline) {
                $worker->read();
                usleep(1000);
            }

            expect($worker->pipes())->toBe([]);
            $worker->getResults();
            unlink($script);
        });
    });

    context('telling a worker who it is', function () {
        it('hands the worker its number, as PHPSPEC_WORKER and as TEST_TOKEN, keeping the rest of the environment', function () {
            $script = tempnam(sys_get_temp_dir(), 'phpspec_worker_spec_');
            file_put_contents($script, '<?php fwrite(STDERR, getenv("PHPSPEC_WORKER") . " " . getenv("TEST_TOKEN") . " " . (getenv("PATH") !== false ? "path" : "none"));');
            $worker = new WorkerProcess($script, number: 3);
            $worker->start();
            $worker->getResults();
            unlink($script);

            expect($worker->getStderr())->toBe('3 3 path');
        });
    });

    context('handing a worker files', function () {
        it('wants a file until it is handed one, again once it ran it, and none once told none is left', function () {
            $script = tempnam(sys_get_temp_dir(), 'phpspec_worker_spec_');
            file_put_contents($script, '<?php while (fgets(STDIN) !== false) { echo "{\\"wire\\":1,\\"ran\\":true}\\n"; } echo "{\\"wire\\":1,\\"end\\":true}\\n";');
            $worker = new WorkerProcess($script);
            $worker->start();

            expect($worker->wantsWork())->toBeTrue();

            $worker->give('spec/A.spec.php', ['spec/A.spec.php']);

            expect($worker->wantsWork())->toBeFalse();

            $deadline = microtime(true) + 5;
            while (!$worker->wantsWork() && microtime(true) < $deadline) {
                $worker->read();
                $worker->takeResults();
                usleep(1000);
            }

            expect($worker->wantsWork())->toBeTrue();

            $worker->finish();

            expect($worker->wantsWork())->toBeFalse();
            expect($worker->getResults())->toBe([]);
            unlink($script);
        });
    });

    context('getResults with no output', function () {
        it('returns empty array when stdout is empty', function () {
            $worker = new WorkerProcess('/dev/null');
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
            $worker = new WorkerProcess('/dev/null');
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
            $worker = new WorkerProcess('/dev/null');
            $ref = new \ReflectionProperty($worker, 'stdout');
            $ref->setValue($worker, "Bootstrap loaded...\n" . $wire->encode($failing) . "\n{\"not\": \"the wire\"}\n" . $wire->ending() . "\n");

            expect($worker->getResults())->toHaveCount(1);
        });

        it('hands over the results whose lines have fully arrived, and keeps a line still arriving for the next read', function () use ($wire, $failing, $feature) {
            $worker = new WorkerProcess('/dev/null');
            $ref = new \ReflectionProperty($worker, 'stdout');
            $second = $wire->encode($feature);
            $ref->setValue($worker, $wire->encode($failing) . "\n" . substr($second, 0, 20));

            expect($worker->takeResults())->toHaveCount(1);
            expect($worker->takeResults())->toHaveCount(0);

            $ref->setValue($worker, $ref->getValue($worker) . substr($second, 20) . "\n");

            expect($worker->takeResults())->toHaveCount(1);
        });

        it('reports the file the worker died running as an error on that file, naming the exit code and what the worker said', function () use ($wire, $failing) {
            $worker = new WorkerProcess('/dev/null');
            $worker->give('spec/App/Basket.spec.php', ['spec/App/Basket.spec.php']);
            (new \ReflectionProperty($worker, 'stdout'))->setValue($worker, $wire->encode($failing) . "\n" . $wire->ran() . "\n");
            $worker->takeResults();
            $worker->give('spec/App/Coupon.spec.php', ['spec/App/Coupon.spec.php']);
            (new \ReflectionProperty($worker, 'stderr'))->setValue($worker, "PHP Fatal error:  Allowed memory size exhausted\n");
            (new \ReflectionProperty($worker, 'exitCode'))->setValue($worker, 255);

            $results = $worker->getResults();

            expect($results)->toHaveCount(1);
            expect($results[0]->getTitle())->toBe('Coupon.spec.php');
            $example = $results[0]->getResults()[0];
            expect($example->isError())->toBeTrue();
            expect($example->getError()->getMessage())->toContain('exited with code 255 before reporting spec/App/Coupon.spec.php');
            expect($example->getError()->getMessage())->toContain('Allowed memory size exhausted');
        });

        it('reports nothing missing for a file whose result arrived before the worker died, run in part or whole', function () use ($wire, $failing) {
            $worker = new WorkerProcess('/dev/null');
            $worker->give('spec/App/Basket.spec.php', ['spec/App/Basket.spec.php:7', 'spec/App/Basket.spec.php:9']);
            (new \ReflectionProperty($worker, 'stdout'))->setValue($worker, $wire->encode($failing) . "\n");
            (new \ReflectionProperty($worker, 'exitCode'))->setValue($worker, 1);

            expect($worker->getResults())->toHaveCount(1);
        });

        it('reports each file it left behind as an error on that file, naming the exit code', function () {
            $worker = new WorkerProcess('/dev/null');
            (new \ReflectionProperty($worker, 'exitCode'))->setValue($worker, 3);

            $messages = array_map(static fn(SpecificationResult $result): string => $result->getResults()[0]->getError()->getMessage(), $worker->leftBehind(['spec/App/A.spec.php', 'spec/App/B.spec.php']));

            expect($messages)->toBe(['The worker exited with code 3 before reporting spec/App/A.spec.php.', 'The worker exited with code 3 before reporting spec/App/B.spec.php.']);
        });

        it('reports nothing missing when the worker ended its report, whatever its exit code', function () use ($wire, $failing) {
            $worker = new WorkerProcess('/dev/null');
            $worker->give('spec/App/Basket.spec.php', ['spec/App/Basket.spec.php']);
            (new \ReflectionProperty($worker, 'stdout'))->setValue($worker, $wire->encode($failing) . "\n" . $wire->ending() . "\n");
            (new \ReflectionProperty($worker, 'exitCode'))->setValue($worker, 1);

            expect($worker->getResults())->toHaveCount(1);
        });
    });

    context('coverage', function () {
        it('loses nothing when its coverage arrived, or when none was asked for', function () {
            $partial = (string) tempnam(sys_get_temp_dir(), 'phpspec_partial_');

            try {
                expect((new WorkerProcess('/dev/null', $partial))->lostCoverage())->toBeNull();
                expect((new WorkerProcess('/dev/null'))->lostCoverage())->toBeNull();
            } finally {
                unlink($partial);
            }
        });

        it('says it exited before handing over its coverage, with what it said', function () {
            $worker = new WorkerProcess('/dev/null', sys_get_temp_dir() . '/phpspec_never_' . uniqid() . '.json');
            (new \ReflectionProperty($worker, 'stderr'))->setValue($worker, "PHP Fatal error:  Allowed memory size exhausted\n");
            (new \ReflectionProperty($worker, 'exitCode'))->setValue($worker, 255);

            expect($worker->lostCoverage())->toBe("Coverage is incomplete: a worker exited with code 255 before handing over its coverage.\nPHP Fatal error:  Allowed memory size exhausted");
        });

        it('says it was stopped with the run before handing over its coverage', function () {
            $worker = new WorkerProcess('/dev/null', sys_get_temp_dir() . '/phpspec_never_' . uniqid() . '.json');

            $worker->terminate();

            expect($worker->lostCoverage())->toBe('Coverage is incomplete: a worker was stopped with the run before handing over its coverage.');
        });
    });

    context('buildCommand', function () {
        it('runs under the php options it is given, before the binary, and asks for the wire format', function () {
            $worker = new WorkerProcess('/path/to/phpspec', phpOptions: ['-d', 'pcov.enabled=0', '-d', 'xdebug.mode=off']);
            $command = $worker->buildCommand();

            expect(array_slice($command, 1, 5))->toBe(['-d', 'pcov.enabled=0', '-d', 'xdebug.mode=off', '/path/to/phpspec']);
            expect($command)->toContain('wire');
            expect($command)->not()->toContain('junit');
        });

        it('names no file on its command line: the worker is handed each over its standard input', function () {
            $command = (new WorkerProcess('/path/to/phpspec'))->buildCommand();
            $run = array_search('run', $command, true);

            expect($command[$run + 1])->toBe('--queue');
        });

        it('requests a partial dump when a coverage partial path is set', function () {
            $worker = new WorkerProcess('/path/to/phpspec', '/tmp/partial-0.json');
            $command = $worker->buildCommand();

            expect($command)->toContain('--coverage-partial=/tmp/partial-0.json');
        });

        it('asks the worker to credit each example only when told to, which only the JSON report reads', function () {
            expect((new WorkerProcess('/path/to/phpspec', '/tmp/partial-1.json'))->buildCommand())->not()->toContain('--coverage-per-example');
            expect((new WorkerProcess('/path/to/phpspec', '/tmp/partial-1.json', perExample: true))->buildCommand())->toContain('--coverage-per-example');
            expect((new WorkerProcess('/path/to/phpspec', perExample: true))->buildCommand())->not()->toContain('--coverage-per-example');
        });

        it('forwards the options that set up the project, a named config and a bootstrap, so a worker runs against the same one', function () {
            $command = (new WorkerProcess('/path/to/phpspec', childOptions: ['--config=custom/my-config.json', '--bootstrap=custom/boot.php']))->buildCommand();

            expect($command)->toContain('--config=custom/my-config.json');
            expect($command)->toContain('--bootstrap=custom/boot.php');
        });

        it('forwards the title filter and the tag expression, so a worker selects as the parent did', function () {
            $command = (new WorkerProcess('/path/to/phpspec', filter: 'adds', tags: '@smoke and not @wip'))->buildCommand();

            expect($command)->toContain('--filter=adds');
            expect($command)->toContain('--tags=@smoke and not @wip');
            expect((new WorkerProcess('/path/to/phpspec'))->buildCommand())->not()->toContain('--filter=');
        });

        it('forwards the stop conditions to the worker, so it halts as the parent would', function () {
            $worker = new WorkerProcess('/path/to/phpspec', stop: new StopConditions(onError: true, onSkipped: true));
            $command = $worker->buildCommand();

            expect($command)->toContain('--stop-on-error');
            expect($command)->toContain('--stop-on-skipped');
            expect($command)->not()->toContain('--stop-on-failure');
        });
    });

    context('getExitCode', function () {
        it('returns null before process runs', function () {
            $worker = new WorkerProcess('/dev/null');
            expect($worker->getExitCode())->toBeNull();
        });
    });

    context('getStderr', function () {
        it('returns empty string initially', function () {
            $worker = new WorkerProcess('/dev/null');
            expect($worker->getStderr())->toBe('');
        });
    });
});
