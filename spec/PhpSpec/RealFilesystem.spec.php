<?php

use PhpSpec\RealFilesystem;

describe(RealFilesystem::class, function() {

    it("checks file existence", function() {
        $fs = new RealFilesystem();
        expect($fs->exists(__FILE__))->toBe(true);
        expect($fs->exists('/nonexistent/path/to/file.xyz'))->toBe(false);
    });

    it("checks if path is a file", function() {
        $fs = new RealFilesystem();
        expect($fs->isFile(__FILE__))->toBe(true);
        expect($fs->isFile(__DIR__))->toBe(false);
    });

    it("checks if path is a directory", function() {
        $fs = new RealFilesystem();
        expect($fs->isDir(__DIR__))->toBe(true);
        expect($fs->isDir(__FILE__))->toBe(false);
    });

    it("reads file contents", function() {
        $fs = new RealFilesystem();
        $content = $fs->read(__FILE__);
        expect($content)->toContain('RealFilesystem');
    });

    it("reads file as lines", function() {
        $fs = new RealFilesystem();
        $lines = $fs->readLines(__FILE__);
        expect(count($lines) > 0)->toBe(true);
        expect($lines[0])->toContain('<?php');
    });

    it("writes and reads back a file", function() {
        $fs = new RealFilesystem();
        $tmp = sys_get_temp_dir() . '/phpspec_real_fs_test_' . uniqid() . '.txt';
        $fs->write($tmp, 'hello phpspec');
        expect($fs->read($tmp))->toBe('hello phpspec');
        unlink($tmp);
    });

    it("creates directory when writing to non-existent path", function() {
        $fs = new RealFilesystem();
        $dir = sys_get_temp_dir() . '/phpspec_real_fs_mkdir_' . uniqid();
        $file = $dir . '/test.txt';
        $fs->write($file, 'content');
        expect($fs->exists($file))->toBe(true);
        unlink($file);
        rmdir($dir);
    });

    it("scans a directory", function() {
        $fs = new RealFilesystem();
        $entries = $fs->scandir(__DIR__);
        expect(in_array('.', $entries))->toBe(true);
        expect(in_array('..', $entries))->toBe(true);
    });

    it("creates directories recursively", function() {
        $fs = new RealFilesystem();
        $dir = sys_get_temp_dir() . '/phpspec_mkdir_' . uniqid() . '/nested';
        $fs->mkdir($dir);
        expect($fs->isDir($dir))->toBe(true);
        rmdir($dir);
        rmdir(dirname($dir));
    });

    it("requires a PHP file", function() {
        $fs = new RealFilesystem();
        $tmp = sys_get_temp_dir() . '/phpspec_require_' . uniqid() . '.php';
        file_put_contents($tmp, '<?php return ["key" => "value"];');
        $result = $fs->requirePhp($tmp);
        expect($result)->toBe(["key" => "value"]);
        unlink($tmp);
    });

});
