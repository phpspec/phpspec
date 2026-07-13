<?php

use PhpSpec\Ai\SymbolInspector;
use PhpSpec\CodeGeneration\ClassLocation;
use PhpSpec\Filesystem;

describe(SymbolInspector::class, function () {

    it('describes an existing class with its real public method signatures', function () {
        $report = (new SymbolInspector())->describe(ClassLocation::class);

        expect($report)->toContain('class PhpSpec\\CodeGeneration\\ClassLocation');
        expect($report)->toContain('public function filePath(): string');
        expect($report)->toContain('public function isAutoloadable(): bool');
    });

    it('reports a symbol that does not exist yet cleanly, not "File not found"', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);

        $report = (new SymbolInspector('src', 'App', $fs))->describe('App\\NotBuiltYet');

        expect($report)->toContain('App\\NotBuiltYet does not exist yet');
        expect($report)->not()->toContain('File not found');
    });

    it('distinguishes a PSR-4 autoload mismatch from a missing class', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);

        $report = (new SymbolInspector('src', '', $fs))->describe('App\\OnDiskButUnmapped');

        expect($report)->toContain('not autoloadable');
        expect($report)->toContain('PSR-4');
    });

    it('accepts a fully-qualified name with a leading backslash', function () {
        $report = (new SymbolInspector())->describe('\\' . ClassLocation::class);

        expect($report)->toContain('class PhpSpec\\CodeGeneration\\ClassLocation');
    });

});
