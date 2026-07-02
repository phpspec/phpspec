<?php

use PhpSpec\Coverage\CoverageDriver;
use PhpSpec\Coverage\PerExampleCollector;

describe(PerExampleCollector::class, function () {

    it('attributes executed lines to the running example', function (CoverageDriver $driver) {
        allow($driver->stop())->toReturn([
            '/project/src/App/Calculator.php' => [12 => 1, 13 => -1, 14 => -2],
        ]);
        $collector = new PerExampleCollector($driver);

        $collector->beginSpec('spec/App/Calculator.spec.php');
        $collector->pushContext('Calculator');
        $collector->beginExample();
        $collector->endExample('adds two numbers');

        expect($collector->getLines())->toBe([
            '/project/src/App/Calculator.php' => [
                12 => ['spec/App/Calculator.spec.php::Calculator > adds two numbers'],
            ],
        ]);
    });

    it('records timing, peak memory and spec file per test', function (CoverageDriver $driver) {
        allow($driver->stop())->toReturn([]);
        $collector = new PerExampleCollector($driver);

        $collector->beginSpec('spec/App/Calculator.spec.php');
        $collector->pushContext('Calculator');
        $collector->beginExample();
        str_repeat('x', 1000);
        $collector->endExample('adds two numbers');

        $tests = $collector->getTests();
        $test = $tests['spec/App/Calculator.spec.php::Calculator > adds two numbers'];
        expect($test['time'])->toBeGreaterThan(0);
        expect($test['memory'])->toBeGreaterThan(0);
        expect($test['spec_file'])->toBe('spec/App/Calculator.spec.php');
    });

    it('merges each cycle into an aggregate usable by the other reports', function (CoverageDriver $driver) {
        allow($driver->stop())->toReturnUsing(function () {
            static $cycle = 0;
            $cycle++;
            return $cycle === 1
                ? ['/project/src/App/Calculator.php' => [12 => 1, 13 => -1, 14 => -2]]
                : ['/project/src/App/Calculator.php' => [12 => -1, 13 => 1, 14 => -2]];
        });
        $collector = new PerExampleCollector($driver);
        $collector->beginSpec('spec/App/Calculator.spec.php');

        $collector->beginExample();
        $collector->endExample('adds');
        $collector->beginExample();
        $collector->endExample('subtracts');

        expect($collector->getAggregate())->toBe([
            '/project/src/App/Calculator.php' => [12 => 1, 13 => 1, 14 => -2],
        ]);
    });

    it('normalises away a leading ./ from the spec path in test identifiers', function (CoverageDriver $driver) {
        allow($driver->stop())->toReturn(['/project/src/App/Calculator.php' => [12 => 1]]);
        $collector = new PerExampleCollector($driver);

        $collector->beginSpec('./spec/App/Calculator.spec.php');
        $collector->beginExample();
        $collector->endExample('adds');

        expect($collector->getLines()['/project/src/App/Calculator.php'][12])->toBe([
            'spec/App/Calculator.spec.php::adds',
        ]);
        expect($collector->getTests()['spec/App/Calculator.spec.php::adds']['spec_file'])
            ->toBe('spec/App/Calculator.spec.php');
    });

    it('attributes a line to every example that executes it, without duplicates', function (CoverageDriver $driver) {
        allow($driver->stop())->toReturn(['/project/src/App/Calculator.php' => [12 => 1]]);
        $collector = new PerExampleCollector($driver);
        $collector->beginSpec('spec/App/Calculator.spec.php');

        $collector->beginExample();
        $collector->endExample('adds');
        $collector->beginExample();
        $collector->endExample('adds');
        $collector->beginExample();
        $collector->endExample('subtracts');

        expect($collector->getLines()['/project/src/App/Calculator.php'][12])->toBe([
            'spec/App/Calculator.spec.php::adds',
            'spec/App/Calculator.spec.php::subtracts',
        ]);
    });

    it('exports its state and merges state from another collector', function (CoverageDriver $driver, CoverageDriver $otherDriver) {
        allow($driver->stop())->toReturn(['/project/src/App/Calculator.php' => [12 => 1, 13 => -1]]);
        $collector = new PerExampleCollector($driver);
        $collector->beginSpec('spec/App/Calculator.spec.php');
        $collector->beginExample();
        $collector->endExample('adds');

        allow($otherDriver->stop())->toReturn(['/project/src/App/Calculator.php' => [12 => 1, 13 => 1]]);
        $other = new PerExampleCollector($otherDriver);
        $other->beginSpec('spec/App/Greeter.spec.php');
        $other->beginExample();
        $other->endExample('greets');

        $collector->applyState($other->toArray());

        expect($collector->getLines()['/project/src/App/Calculator.php'][12])->toBe([
            'spec/App/Calculator.spec.php::adds',
            'spec/App/Greeter.spec.php::greets',
        ]);
        expect($collector->getAggregate()['/project/src/App/Calculator.php'])->toBe([12 => 1, 13 => 1]);
        expect($collector->getTests())->toHaveKey('spec/App/Greeter.spec.php::greets');
        expect($collector->getTests())->toHaveKey('spec/App/Calculator.spec.php::adds');
    });
});
