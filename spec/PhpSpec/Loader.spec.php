<?php

use PhpSpec\Loader;
use PhpSpec\Filesystem;
use PhpSpec\Suite;

describe(Loader::class, function () {

    it("instantiates", function (Filesystem $fs) {
        $loader = new Loader($fs);
        expect($loader)->toBeAnInstanceOf(Loader::class);
    });

    it("returns empty suite for non-existent path", function (Filesystem $fs) {
        $suite = (new Loader($fs))->load('./missing');
        expect($suite)->toBeAnInstanceOf(Suite::class);
    });

    it("loads a single spec file", function (Filesystem $fs) {
        allow($fs->isFile())->toReturnUsing(fn(string $path) => $path === './spec/Foo.spec.php');

        $suite = (new Loader($fs))->load('./spec/Foo.spec.php');

        expect($suite)->toBeAnInstanceOf(Suite::class);
    });

    it("loads spec files from a directory", function (Filesystem $fs) {
        $files = ['.', '..', 'One.spec.php', 'Two.spec.php'];
        $dirs = ['./spec'];

        allow($fs->isFile())->toReturnUsing(fn(string $p) => str_ends_with($p, '.spec.php'));
        allow($fs->isDir())->toReturnUsing(fn(string $p) => in_array($p, $dirs));
        allow($fs->scandir())->toReturn($files);

        $suite = (new Loader($fs))->load('./spec');

        expect($suite)->toBeAnInstanceOf(Suite::class);
    });

    it("recurses into subdirectories", function (Filesystem $fs) {
        $dirs = ['./spec', './spec/Sub'];

        allow($fs->isFile())->toReturnUsing(fn(string $p) => str_ends_with($p, '.spec.php'));
        allow($fs->isDir())->toReturnUsing(fn(string $p) => in_array($p, $dirs));
        allow($fs->scandir())->toReturnUsing(fn(string $p) => match ($p) {
            './spec' => ['.', '..', 'Sub'],
            './spec/Sub' => ['.', '..', 'Bar.spec.php'],
            default => ['.', '..'],
        });

        $suite = (new Loader($fs))->load('./spec');

        expect($suite)->toBeAnInstanceOf(Suite::class);
    });

    it("defaults to ./spec when null is passed", function (Filesystem $fs) {
        allow($fs->isDir())->toReturnUsing(fn(string $p) => $p === './spec');
        allow($fs->scandir())->toReturn(['.', '..']);

        $suite = (new Loader($fs))->load(null);

        expect($suite)->toBeAnInstanceOf(Suite::class);
    });

    it("loads multiple comma-separated paths", function (Filesystem $fs) {
        allow($fs->isDir())->toReturnUsing(fn(string $p) => $p === './spec' || $p === './features');
        allow($fs->isFile())->toReturn(false);
        allow($fs->scandir())->toReturn(['.', '..']);

        $suite = (new Loader($fs))->load('./spec,./features');

        expect($suite)->toBeAnInstanceOf(Suite::class);
    });

    it("ignores non-spec files", function (Filesystem $fs) {
        allow($fs->isFile())->toReturnUsing(fn(string $p) => str_ends_with($p, '.php'));
        allow($fs->isDir())->toReturnUsing(fn(string $p) => $p === './spec');
        allow($fs->scandir())->toReturn(['.', '..', 'Helper.php', 'Test.spec.php']);

        $suite = (new Loader($fs))->load('./spec');

        expect($suite)->toBeAnInstanceOf(Suite::class);
    });

    it("loads spec files with custom suffix", function (Filesystem $fs) {
        $files = ['.', '..', 'OneSpec.php', 'Two.spec.php'];
        $dirs = ['./spec'];

        allow($fs->isFile())->toReturnUsing(fn(string $p) => str_ends_with($p, '.php'));
        allow($fs->isDir())->toReturnUsing(fn(string $p) => in_array($p, $dirs));
        allow($fs->scandir())->toReturn($files);

        $suite = (new Loader($fs, 'Spec.php'))->load('./spec');

        expect($suite)->toBeAnInstanceOf(Suite::class);
    });

    it("filters specs by path", function (Filesystem $fs) {
        allow($fs->isFile())->toReturnUsing(fn(string $p) => str_ends_with($p, '.spec.php'));
        allow($fs->isDir())->toReturnUsing(fn(string $p) => $p === './spec');
        allow($fs->scandir())->toReturn(['.', '..', 'One.spec.php', 'Two.spec.php']);

        $suite = (new Loader($fs))->load('./spec', 'One');

        expect($suite)->toBeAnInstanceOf(Suite::class);
    });

    it("detects feature path by extension", function (Filesystem $fs) {
        allow($fs->isFile())->toReturnUsing(fn(string $p) => str_ends_with($p, '.feature'));
        allow($fs->isDir())->toReturnUsing(fn(string $p) => false);
        allow($fs->read())->toReturn("Feature: Test\n  Scenario: Basic\n    Given a step\n");

        $suite = (new Loader($fs))->load('test.feature');

        expect($suite)->toBeAnInstanceOf(Suite::class);
    });

    it("detects feature path by directory name", function (Filesystem $fs) {
        allow($fs->isFile())->toReturnUsing(fn(string $p) => str_ends_with($p, '.feature'));
        allow($fs->isDir())->toReturnUsing(fn(string $p) => $p === './features');
        allow($fs->scandir())->toReturn(['.', '..', 'greeting.feature']);
        allow($fs->read())->toReturn("Feature: Test\n  Scenario: Basic\n    Given a step\n");

        $suite = (new Loader($fs))->load('./features');

        expect($suite)->toBeAnInstanceOf(Suite::class);
    });

    it("loads feature with steps directory without step files", function (Filesystem $fs) {
        allow($fs->isFile())->toReturnUsing(fn(string $p) =>
            str_ends_with($p, '.feature')
        );
        allow($fs->isDir())->toReturnUsing(fn(string $p) =>
            $p === './features' || $p === './features/steps'
        );
        allow($fs->scandir())->toReturnUsing(fn(string $p) => match ($p) {
            './features' => ['.', '..', 'greeting.feature', 'steps'],
            './features/steps' => ['.', '..'],
            default => ['.', '..'],
        });
        allow($fs->read())->toReturn("Feature: Test\n  Scenario: Basic\n    Given a step\n");

        $suite = (new Loader($fs))->load('./features');

        expect($suite)->toBeAnInstanceOf(Suite::class);
    });

    it("returns empty suite for non-existent feature directory", function (Filesystem $fs) {
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);

        $suite = (new Loader($fs))->load('./features');

        expect($suite)->toBeAnInstanceOf(Suite::class);
    });

    it("loads a single feature file with steps dir", function (Filesystem $fs) {
        allow($fs->isFile())->toReturnUsing(fn(string $p) => str_ends_with($p, '.feature'));
        allow($fs->isDir())->toReturnUsing(fn(string $p) => false);
        allow($fs->read())->toReturn("Feature: Single\n  Scenario: One\n    Given step\n");

        $suite = (new Loader($fs))->load('./features/test.feature');

        expect($suite)->toBeAnInstanceOf(Suite::class);
    });

    it("scans nested feature subdirectories", function (Filesystem $fs) {
        allow($fs->isFile())->toReturnUsing(fn(string $p) => str_ends_with($p, '.feature'));
        allow($fs->isDir())->toReturnUsing(fn(string $p) => match ($p) {
            './features' => true,
            './features/scenarios' => true,
            default => false,
        });
        allow($fs->scandir())->toReturnUsing(fn(string $p) => match ($p) {
            './features' => ['.', '..', 'scenarios'],
            './features/scenarios' => ['.', '..', 'test.feature'],
            default => ['.', '..'],
        });
        allow($fs->read())->toReturn("Feature: Nested\n  Scenario: Basic\n    Given a step\n");

        $suite = (new Loader($fs))->load('./features');

        expect($suite)->toBeAnInstanceOf(Suite::class);
    });

    it("walks up directories to find steps when loading a single feature file", function (Filesystem $fs) {
        allow($fs->isFile())->toReturnUsing(fn(string $p) => match ($p) {
            './features/scenarios/test.feature' => true,
            default => false,
        });
        allow($fs->isDir())->toReturnUsing(fn(string $p) => match ($p) {
            './features/steps' => true,
            default => false,
        });
        allow($fs->scandir())->toReturnUsing(fn(string $p) => match ($p) {
            './features/steps' => ['.', '..'],
            default => ['.', '..'],
        });
        allow($fs->read())->toReturn("Feature: Walk\n  Scenario: Up\n    Given a step\n");

        $suite = (new Loader($fs))->load('./features/scenarios/test.feature');

        expect($suite)->toBeAnInstanceOf(Suite::class);
    });

    it("filters features by path", function (Filesystem $fs) {
        allow($fs->isFile())->toReturnUsing(fn(string $p) => str_ends_with($p, '.feature'));
        allow($fs->isDir())->toReturnUsing(fn(string $p) => $p === './features');
        allow($fs->scandir())->toReturn(['.', '..', 'greeting.feature', 'other.feature']);
        allow($fs->read())->toReturn("Feature: Test\n  Scenario: Basic\n    Given a step\n");

        $suite = (new Loader($fs))->load('./features', 'greeting');

        expect($suite)->toBeAnInstanceOf(Suite::class);
    });

});
