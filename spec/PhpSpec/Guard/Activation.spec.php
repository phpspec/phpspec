<?php

use PhpSpec\Filesystem;
use PhpSpec\Guard\Activation;

describe(Activation::class, function () {

    // A project's config is something a person wrote. Turning guard on must
    // give it back with their comments and their ordering intact.
    it('appends a guard block, leaving what was written alone', function (Filesystem $fs) {
        $written = null;
        allow($fs->exists())->toReturnUsing(fn(string $path) => $path === '/app/phpspec.yml');
        allow($fs->read())->toReturn("# the paths this project uses\nspec_path: spec\n");
        allow($fs->write())->toReturnUsing(function (string $path, string $content) use (&$written) {
            $written = $content;
        });

        expect((new Activation($fs, '/app'))->turnOn())->toBe('/app/phpspec.yml');
        expect($written)->toBe("# the paths this project uses\nspec_path: spec\n\nguard:\n  status: active\n");
    });

    it('turns an existing guard block on rather than adding a second one', function (Filesystem $fs) {
        $written = null;
        allow($fs->exists())->toReturnUsing(fn(string $path) => $path === '/app/phpspec.yml');
        allow($fs->read())->toReturn("guard:\n  status: off\n  scope: story\nspec_path: spec\n");
        allow($fs->write())->toReturnUsing(function (string $path, string $content) use (&$written) {
            $written = $content;
        });

        (new Activation($fs, '/app'))->turnOn();

        expect($written)->toBe("guard:\n  status: active\n  scope: story\nspec_path: spec\n");
    });

    it('adds the status to a guard block that has none', function (Filesystem $fs) {
        $written = null;
        allow($fs->exists())->toReturnUsing(fn(string $path) => $path === '/app/phpspec.yml');
        allow($fs->read())->toReturn("guard:\n  scope: story\n");
        allow($fs->write())->toReturnUsing(function (string $path, string $content) use (&$written) {
            $written = $content;
        });

        (new Activation($fs, '/app'))->turnOn();

        expect($written)->toBe("guard:\n  status: active\n  scope: story\n");
    });

    it('writes a config when the project keeps none', function (Filesystem $fs) {
        $written = null;
        allow($fs->exists())->toReturn(false);
        allow($fs->write())->toReturnUsing(function (string $path, string $content) use (&$written) {
            $written = $content;
        });

        expect((new Activation($fs, '/app'))->turnOn())->toBe('/app/phpspec.yml');
        expect($written)->toBe("guard:\n  status: active\n");
    });

    it('sets the status in a JSON config, which has no comments to lose', function (Filesystem $fs) {
        $written = null;
        allow($fs->exists())->toReturnUsing(fn(string $path) => $path === '/app/phpspec.json');
        allow($fs->read())->toReturn('{"spec_path": "spec"}');
        allow($fs->write())->toReturnUsing(function (string $path, string $content) use (&$written) {
            $written = $content;
        });

        (new Activation($fs, '/app'))->turnOn();

        expect(json_decode((string) $written, true))->toBe(['spec_path' => 'spec', 'guard' => ['status' => 'active']]);
    });

    it('refuses to rewrite a config that is code', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => $path === '/app/phpspec.php');

        expect((new Activation($fs, '/app'))->turnOn())->toBeNull();
    });

});
