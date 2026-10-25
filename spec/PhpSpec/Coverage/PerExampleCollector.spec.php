<?php

use PhpSpec\Coverage\CoverageDriver;
use PhpSpec\Coverage\PerExampleCollector;

describe(PerExampleCollector::class, function () {

    it('attributes executed lines to the running example', function (CoverageDriver $driver) {
        allow($driver->start());
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
        allow($driver->start());
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
        allow($driver->start());
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

    it('pauses after each example when crediting none, and collects once when it finishes', function (CoverageDriver $driver) {
        allow($driver->start());
        allow($driver->pause());
        allow($driver->stop())->toReturn(['/project/src/App/Calculator.php' => [12 => 1, 13 => -1]]);
        $collector = new PerExampleCollector($driver, crediting: false);

        $collector->beginSpec('spec/App/Calculator.spec.php');
        $collector->beginExample();
        $collector->endExample('adds');
        $collector->beginExample();
        $collector->endExample('subtracts');
        $collector->finish();
        $collector->finish();

        expect($driver->pause())->toHaveBeenCalled()->twice();
        expect($driver->stop())->toHaveBeenCalled()->once();
        expect($collector->getAggregate())->toBe(['/project/src/App/Calculator.php' => [12 => 1, 13 => -1]]);
        expect($collector->getLines())->toBe([]);
        expect($collector->getTests())->toBe([]);
    });

    it('counts an example run inside another as part of the outer one', function (CoverageDriver $driver) {
        allow($driver->start());
        allow($driver->stop())->toReturn(['/project/src/App/Calculator.php' => [12 => 1]]);
        $collector = new PerExampleCollector($driver);

        $collector->beginSpec('spec/App/Calculator.spec.php');
        $collector->pushContext('Calculator');
        $collector->beginExample();
        $collector->beginSpec('/tmp/Inner.spec.php');
        $collector->pushContext('Inner');
        $collector->beginExample();
        $collector->endExample('runs inside');
        $collector->popContext();
        $collector->endExample('runs a spec');

        expect($driver->start())->toHaveBeenCalled()->once();
        expect($driver->stop())->toHaveBeenCalled()->once();
        expect(array_keys($collector->getTests()))->toBe(['spec/App/Calculator.spec.php::Calculator > runs a spec']);
    });

    it('normalises away a leading ./ from the spec path in test identifiers', function (CoverageDriver $driver) {
        allow($driver->start());
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
        allow($driver->start());
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

    it('exports each line by the place of its tests in the test list, not by their names', function (CoverageDriver $driver) {
        allow($driver->start());
        allow($driver->stop())->toReturn(['/project/src/App/Calculator.php' => [12 => 1]]);
        $collector = new PerExampleCollector($driver);
        $collector->beginSpec('spec/App/Calculator.spec.php');

        $collector->beginExample();
        $collector->endExample('adds');
        $collector->beginExample();
        $collector->endExample('subtracts');

        $state = $collector->toArray();
        expect(array_keys($state['tests']))->toBe(['spec/App/Calculator.spec.php::adds', 'spec/App/Calculator.spec.php::subtracts']);
        expect($state['lines'])->toBe(['/project/src/App/Calculator.php' => [12 => [0, 1]]]);
    });

    it('exports its state and merges state from another collector', function (CoverageDriver $driver, CoverageDriver $otherDriver) {
        allow($driver->start());
        allow($driver->stop())->toReturn(['/project/src/App/Calculator.php' => [12 => 1, 13 => -1]]);
        $collector = new PerExampleCollector($driver);
        $collector->beginSpec('spec/App/Calculator.spec.php');
        $collector->beginExample();
        $collector->endExample('adds');

        allow($otherDriver->start());

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

    it('merges what another process recorded by what each of them compiled', function (CoverageDriver $driver, CoverageDriver $otherDriver) {
        $file = sys_get_temp_dir() . '/phpspec_recorded_' . uniqid() . '.php';
        file_put_contents($file, "<?php\nfunction filtered(\$id) {\n    return [\n        'id' => \$id,\n        'on' => ON,\n    ];\n}\n");

        try {
            allow($driver->start());
            allow($driver->stop())->toReturn([$file => [3 => 1, 4 => 1, 6 => 1]]);
            $collector = new PerExampleCollector($driver);
            $collector->beginSpec('spec/Filtered.spec.php');
            $collector->beginExample();
            $collector->endExample('filters');

            allow($otherDriver->start());
            allow($otherDriver->stop())->toReturn([$file => [3 => -1, 4 => -1, 5 => -1, 6 => -1]]);
            $other = new PerExampleCollector($otherDriver);
            $other->beginSpec('spec/Other.spec.php');
            $other->beginExample();
            $other->endExample('does not filter');

            $collector->applyState($other->toArray());

            expect($collector->getAggregate()[$file])->toBe([3 => 1, 4 => 1, 6 => 1, 5 => -2]);
            expect($collector->toArray()['aggregate'][$file])->toBe([3 => 1, 4 => 1, 6 => 1]);
        } finally {
            unlink($file);
        }
    });
});
