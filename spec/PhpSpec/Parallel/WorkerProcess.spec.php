<?php

use PhpSpec\Parallel\WorkerProcess;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\StepResult;

describe(WorkerProcess::class, function () {

    context('getResults with no output', function () {
        it('returns empty array when stdout is empty', function () {
            $worker = new WorkerProcess([], '/dev/null');
            $results = $worker->getResults();

            expect($results)->toHaveCount(0);
        });
    });

    context('getResults with non-XML output', function () {
        it('returns empty array when stdout has no XML', function () {
            $worker = new WorkerProcess([], '/dev/null');
            $ref = new \ReflectionProperty($worker, 'stdout');
            $ref->setValue($worker, 'some random output without xml');

            $results = $worker->getResults();

            expect($results)->toHaveCount(0);
        });
    });

    context('getResults with invalid XML', function () {
        it('returns empty array for malformed XML', function () {
            $worker = new WorkerProcess([], '/dev/null');
            $ref = new \ReflectionProperty($worker, 'stdout');
            $ref->setValue($worker, '<?xml version="1.0"?><broken');

            $results = $worker->getResults();

            expect($results)->toHaveCount(0);
        });
    });

    context('parsing spec results from JUnit XML', function () {
        it('parses passing testcases into ExampleResult', function () {
            $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <testsuites>
              <testsuite name="Calculator" tests="2" failures="0" errors="0">
                <testcase name="adds numbers" classname="Calculator"/>
                <testcase name="subtracts numbers" classname="Calculator"/>
              </testsuite>
            </testsuites>
            XML;

            $worker = new WorkerProcess([], '/dev/null');
            $ref = new \ReflectionProperty($worker, 'stdout');
            $ref->setValue($worker, $xml);

            $results = $worker->getResults();

            expect($results)->toHaveCount(1);
            expect($results[0])->toBeAnInstanceOf(SpecificationResult::class);
            expect($results[0]->getTitle())->toBe('Calculator');

            $examples = $results[0]->getResults();
            expect($examples)->toHaveCount(2);
            expect($examples[0]->getTitle())->toBe('adds numbers');
            expect($examples[0]->isFailure())->toBeFalse();
            expect($examples[0]->isError())->toBeFalse();
        });

        it('parses failure testcases', function () {
            $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <testsuites>
              <testsuite name="Calculator" tests="1" failures="1" errors="0">
                <testcase name="fails">
                  <failure message="Expected 1 to be 2"/>
                </testcase>
              </testsuite>
            </testsuites>
            XML;

            $worker = new WorkerProcess([], '/dev/null');
            $ref = new \ReflectionProperty($worker, 'stdout');
            $ref->setValue($worker, $xml);

            $results = $worker->getResults();
            $examples = $results[0]->getResults();

            expect($examples[0]->isFailure())->toBeTrue();
            expect($examples[0]->getMessage())->toContain('Expected 1 to be 2');
        });

        it('parses error testcases', function () {
            $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <testsuites>
              <testsuite name="Calculator" tests="1" failures="0" errors="1">
                <testcase name="errors">
                  <error message="Something went wrong"/>
                </testcase>
              </testsuite>
            </testsuites>
            XML;

            $worker = new WorkerProcess([], '/dev/null');
            $ref = new \ReflectionProperty($worker, 'stdout');
            $ref->setValue($worker, $xml);

            $results = $worker->getResults();
            $examples = $results[0]->getResults();

            expect($examples[0]->isError())->toBeTrue();
        });

        it('parses skipped testcases', function () {
            $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <testsuites>
              <testsuite name="Calculator" tests="1" failures="0" errors="0" skipped="1">
                <testcase name="skipped">
                  <skipped/>
                </testcase>
              </testsuite>
            </testsuites>
            XML;

            $worker = new WorkerProcess([], '/dev/null');
            $ref = new \ReflectionProperty($worker, 'stdout');
            $ref->setValue($worker, $xml);

            $results = $worker->getResults();
            $examples = $results[0]->getResults();

            expect($examples[0]->isPending() || $examples[0]->isSkipped())->toBeTrue();
        });
    });

    context('parsing feature results from JUnit XML', function () {
        it('parses feature testsuites with type=feature', function () {
            $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <testsuites>
              <testsuite name="User login" type="feature">
                <testsuite name="Successful login" type="scenario">
                  <testcase name="Given a registered user" classname="User login"/>
                  <testcase name="When the user logs in" classname="User login"/>
                  <testcase name="Then the user sees the dashboard" classname="User login"/>
                </testsuite>
              </testsuite>
            </testsuites>
            XML;

            $worker = new WorkerProcess([], '/dev/null');
            $ref = new \ReflectionProperty($worker, 'stdout');
            $ref->setValue($worker, $xml);

            $results = $worker->getResults();

            expect($results)->toHaveCount(1);
            expect($results[0])->toBeAnInstanceOf(FeatureResult::class);
            expect($results[0]->getTitle())->toBe('User login');

            $scenarios = $results[0]->getResults();
            expect($scenarios)->toHaveCount(1);
            expect($scenarios[0])->toBeAnInstanceOf(ScenarioResult::class);
            expect($scenarios[0]->getTitle())->toBe('Successful login');

            $steps = $scenarios[0]->getResults();
            expect($steps)->toHaveCount(3);
            expect($steps[0])->toBeAnInstanceOf(StepResult::class);
            expect($steps[0]->getTitle())->toBe('Given a registered user');
            expect($steps[0]->isPassed())->toBeTrue();
        });

        it('parses pending steps with skipped element', function () {
            $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <testsuites>
              <testsuite name="Feature" type="feature">
                <testsuite name="Scenario" type="scenario">
                  <testcase name="Given a pending step" classname="Feature">
                    <skipped/>
                  </testcase>
                </testsuite>
              </testsuite>
            </testsuites>
            XML;

            $worker = new WorkerProcess([], '/dev/null');
            $ref = new \ReflectionProperty($worker, 'stdout');
            $ref->setValue($worker, $xml);

            $results = $worker->getResults();
            $steps = $results[0]->getResults()[0]->getResults();

            expect($steps[0]->isPending())->toBeTrue();
        });

        it('parses failed steps with failure element', function () {
            $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <testsuites>
              <testsuite name="Feature" type="feature">
                <testsuite name="Scenario" type="scenario">
                  <testcase name="Then it fails" classname="Feature">
                    <failure message="Assertion failed"/>
                  </testcase>
                </testsuite>
              </testsuite>
            </testsuites>
            XML;

            $worker = new WorkerProcess([], '/dev/null');
            $ref = new \ReflectionProperty($worker, 'stdout');
            $ref->setValue($worker, $xml);

            $results = $worker->getResults();
            $steps = $results[0]->getResults()[0]->getResults();

            expect($steps[0]->isFailure())->toBeTrue();
            expect($steps[0]->getError()->getMessage())->toBe('Assertion failed');
        });
    });

    context('parsing mixed spec and feature results', function () {
        it('parses both types in a single XML document', function () {
            $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <testsuites>
              <testsuite name="Calculator" tests="1">
                <testcase name="adds" classname="Calculator"/>
              </testsuite>
              <testsuite name="User login" type="feature">
                <testsuite name="Happy path" type="scenario">
                  <testcase name="Given a user" classname="User login"/>
                </testsuite>
              </testsuite>
            </testsuites>
            XML;

            $worker = new WorkerProcess([], '/dev/null');
            $ref = new \ReflectionProperty($worker, 'stdout');
            $ref->setValue($worker, $xml);

            $results = $worker->getResults();

            expect($results)->toHaveCount(2);
            expect($results[0])->toBeAnInstanceOf(SpecificationResult::class);
            expect($results[1])->toBeAnInstanceOf(FeatureResult::class);
        });
    });

    context('getResults strips non-XML prefix', function () {
        it('finds XML even with leading output', function () {
            $xml = 'Bootstrap loaded...' . "\n" . '<?xml version="1.0" encoding="UTF-8"?>'
                . '<testsuites><testsuite name="Spec" tests="1">'
                . '<testcase name="works" classname="Spec"/>'
                . '</testsuite></testsuites>';

            $worker = new WorkerProcess([], '/dev/null');
            $ref = new \ReflectionProperty($worker, 'stdout');
            $ref->setValue($worker, $xml);

            $results = $worker->getResults();

            expect($results)->toHaveCount(1);
            expect($results[0]->getTitle())->toBe('Spec');
        });
    });

    context('buildCommand', function () {
        it('disables xdebug and produces junit output by default', function () {
            $worker = new WorkerProcess(['spec/A.spec.php'], '/path/to/phpspec');
            $command = $worker->buildCommand();

            expect($command)->toContain('xdebug.mode=off');
            expect($command)->toContain('spec/A.spec.php');
        });

        it('enables xdebug coverage and requests a partial dump when a coverage partial path is set', function () {
            $worker = new WorkerProcess(['spec/A.spec.php'], '/path/to/phpspec', '/tmp/partial-0.json');
            $command = $worker->buildCommand();

            expect($command)->toContain('xdebug.mode=coverage');
            expect($command)->toContain('--coverage-partial=/tmp/partial-0.json');
        });

        it('forwards the explicit config path to the worker', function () {
            $worker = new WorkerProcess(['spec/A.spec.php'], '/path/to/phpspec', configPath: 'custom/my-config.json');
            $command = $worker->buildCommand();

            expect($command)->toContain('--config=custom/my-config.json');
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
