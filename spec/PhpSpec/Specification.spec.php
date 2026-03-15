<?php

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

});
