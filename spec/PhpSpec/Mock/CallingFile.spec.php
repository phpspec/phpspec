<?php

use PhpSpec\Mock\CallingFile;

describe(CallingFile::class, function () {
    this('written', fn() => function (string $code): string {
        $path = tempnam(sys_get_temp_dir(), 'phpspec_calling_file_');
        file_put_contents($path, $code);

        return $path;
    });

    afterEach(function () {
        array_map('unlink', glob(sys_get_temp_dir() . '/phpspec_calling_file_*') ?: []);
    });

    it('declares strict types where the file opens by declaring them, after any comment or shebang', function () {
        expect((new CallingFile(($this->written)("<?php declare(strict_types=1);\n")))->declaresStrictTypes())->toBeTrue();
        expect((new CallingFile(($this->written)("<?php\n\n/*\n * Licence.\n */\n\n// one more\ndeclare(strict_types=1);\n")))->declaresStrictTypes())->toBeTrue();
        expect((new CallingFile(($this->written)("#!/usr/bin/env php\n<?php\ndeclare( strict_types = 1 );\n")))->declaresStrictTypes())->toBeTrue();
    });

    it('does not where it declares none, turns them off, or cannot be read', function () {
        expect((new CallingFile(($this->written)("<?php\n\nnamespace App;\n")))->declaresStrictTypes())->toBeFalse();
        expect((new CallingFile(($this->written)("<?php\ndeclare(strict_types=0);\n")))->declaresStrictTypes())->toBeFalse();
        expect((new CallingFile('/nowhere/at/all.php'))->declaresStrictTypes())->toBeFalse();
        expect((new CallingFile(''))->declaresStrictTypes())->toBeFalse();
    });
});
