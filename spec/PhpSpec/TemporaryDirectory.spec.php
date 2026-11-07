<?php

use PhpSpec\TemporaryDirectory;

describe(TemporaryDirectory::class, function () {

    it('makes a new empty directory, named by its prefix, that no other one shares', function () {
        $first = TemporaryDirectory::create('phpspec_tmpdir_spec_');
        $second = TemporaryDirectory::create('phpspec_tmpdir_spec_');

        expect(is_dir($first->path))->toBeTrue();
        expect(scandir($first->path))->toBe(['.', '..']);
        expect(basename($first->path))->toStartWith('phpspec_tmpdir_spec_');
        expect($first->path)->not()->toBe($second->path);

        $first->remove();
        $second->remove();
    });

    it('removes itself with everything in it', function () {
        $directory = TemporaryDirectory::create('phpspec_tmpdir_spec_');
        mkdir($directory->path . '/nested');
        file_put_contents($directory->path . '/nested/file.txt', 'kept until now');

        $directory->remove();

        expect(is_dir($directory->path))->toBeFalse();
    });

    it('is made under the directory it is given', function () {
        $parent = TemporaryDirectory::create('phpspec_tmpdir_spec_');
        $child = TemporaryDirectory::create('inner_', $parent->path);

        expect(dirname($child->path))->toBe($parent->path);

        $parent->remove();
    });
});
