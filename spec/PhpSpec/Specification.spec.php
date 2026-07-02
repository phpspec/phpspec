<?php

use PhpSpec\Coverage\CoverageDriver;
use PhpSpec\Coverage\CoverageRegistry;
use PhpSpec\Coverage\PerExampleCollector;
use PhpSpec\Specification;
use PhpSpec\Specification\Example;

describe(Specification::class, function() {

    it("manages spec blocks", function() {
        $spec = new Specification(__FILE__);
        expect($spec->getSpecBlocks())->toHaveCount(0);

        $example = new Example("test", function() {});
        $spec->addSpecBlock($example);
        expect($spec->getSpecBlocks())->toHaveCount(1);
    });

    it("resets spec blocks", function() {
        $spec = new Specification(__FILE__);
        $example = new Example("test", function() {});
        $spec->addSpecBlock($example);
        expect($spec->getSpecBlocks())->toHaveCount(1);

        $spec->reset();
        expect($spec->getSpecBlocks())->toHaveCount(0);
    });

    it("returns the path", function() {
        $spec = new Specification('/some/path/MyClass.spec.php');
        expect($spec->getPath())->toBe('/some/path/MyClass.spec.php');
    });

    it("announces the spec file to the active coverage collector", function (CoverageDriver $driver) {
        allow($driver->stop())->toReturn([]);
        $collector = new PerExampleCollector($driver);
        CoverageRegistry::activate($collector);

        $file = sys_get_temp_dir() . '/phpspec_spec_coverage_' . uniqid() . '.spec.php';
        file_put_contents($file, '<?php describe("Sample", function () { it("works", function () {}); });');

        try {
            (new Specification($file))->run();
        } finally {
            CoverageRegistry::reset();
            unlink($file);
        }

        // Test identifiers always use forward slashes, also on Windows
        $expectedId = str_replace('\\', '/', $file) . '::Sample > works';
        expect($collector->getTests())->toHaveKey($expectedId);
    });

});
