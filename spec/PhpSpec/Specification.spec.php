<?php

use PhpSpec\FilterRegistry;
use PhpSpec\Specification;
use PhpSpec\Specification\Example;
use PhpSpec\TitleFilter;

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

    it("announces its path to the active title filter so path matches keep all examples", function() {
        FilterRegistry::activate(new TitleFilter('Targeted'));

        $file = sys_get_temp_dir() . '/phpspec_Targeted_' . uniqid() . '.spec.php';
        file_put_contents($file, '<?php describe("Sample", function () { it("unrelated title", function () {}); });');

        try {
            $result = (new Specification($file))->run();
        } finally {
            FilterRegistry::reset();
            unlink($file);
        }

        expect($result->getResults()[0]->getResults())->toHaveCount(1);
    });

});
