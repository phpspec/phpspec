<?php

use PhpSpec\Parallel\PhpSetup;

describe(PhpSetup::class, function () {

    it('hands a php started afresh each setting it would not have', function () {
        $parent = new PhpSetup(['memory_limit' => '1G', 'precision' => '14', 'zend.assertions' => '1', 'include_path' => '.:/opt/lib']);
        $fresh = new PhpSetup(['memory_limit' => '128M', 'precision' => '14', 'zend.assertions' => '-1', 'include_path' => '.:/usr/share/php']);

        expect($parent->over($fresh))->toBe(['-d', 'memory_limit=1G', '-d', 'zend.assertions=1', '-d', 'include_path=.:/opt/lib']);
    });

    it('hands over a setting emptied', function () {
        expect((new PhpSetup(['auto_prepend_file' => null]))->over(new PhpSetup(['auto_prepend_file' => '/opt/prepend.php'])))->toBe(['-d', 'auto_prepend_file=']);
    });

    it('loads each extension it lacks by its file name, a Zend one as a Zend extension, with the settings that come with it', function () {
        $parent = new PhpSetup(
            ['memory_limit' => '128M', 'pcov.enabled' => '1', 'xdebug.mode' => 'coverage'],
            ['Core', 'SimpleXML', 'pcov', 'xdebug', 'Zend OPcache'],
            ['Xdebug', 'Zend OPcache'],
        );
        $fresh = new PhpSetup(['memory_limit' => '128M'], ['Core'], []);

        expect($parent->over($fresh))->toBe([
            '-d', 'extension=simplexml',
            '-d', 'extension=pcov',
            '-d', 'zend_extension=xdebug',
            '-d', 'zend_extension=opcache',
            '-d', 'pcov.enabled=1',
            '-d', 'xdebug.mode=coverage',
        ]);
    });

    it('loads nothing a php started afresh already has', function () {
        $parent = new PhpSetup([], ['Core', 'pcov', 'xdebug'], ['Xdebug']);

        expect($parent->over(new PhpSetup([], ['core', 'PCOV', 'xdebug'], ['Xdebug'])))->toBe([]);
    });
});
