<?php

use PhpSpec\CodeGeneration\SurroundingCode;
use PhpSpec\Filesystem;

describe(SurroundingCode::class, function () {

    it("instantiates", function (Filesystem $fs) {
        $code = new SurroundingCode('/app/Foo.php', 10, 3, 3, $fs);
        expect($code)->toBeAnInstanceOf(SurroundingCode::class);
    });

    it("returns surrounding lines as array", function (Filesystem $fs) {
        $lines = ["line1\n", "line2\n", "line3\n", "line4\n", "line5\n", "line6\n", "line7\n", "line8\n", "line9\n", "line10\n"];
        allow($fs->readLines('/app/Foo.php'))->toReturn($lines);

        $code = new SurroundingCode('/app/Foo.php', 5, 2, 2, $fs);
        $result = $code->toArray();

        expect($result)->toHaveCount(5);
        expect(array_key_first($result))->toBe(3);
        expect(array_key_last($result))->toBe(7);
    });

    it("handles lines near the start of a file", function (Filesystem $fs) {
        $lines = ["line1\n", "line2\n", "line3\n", "line4\n", "line5\n"];
        allow($fs->readLines('/app/Bar.php'))->toReturn($lines);

        $code = new SurroundingCode('/app/Bar.php', 1, 3, 3, $fs);
        $result = $code->toArray();

        expect(array_key_first($result))->toBe(1);
    });

    it("returns empty array for empty file path", function (Filesystem $fs) {
        $code = new SurroundingCode('', 10, 3, 3, $fs);
        $result = $code->toArray();

        expect($result)->toBe([]);
    });

    it("returns correct number of surrounding lines", function (Filesystem $fs) {
        $lines = [];
        for ($i = 1; $i <= 20; $i++) {
            $lines[] = "line{$i}\n";
        }
        allow($fs->readLines('/app/Big.php'))->toReturn($lines);

        $code = new SurroundingCode('/app/Big.php', 10, 3, 3, $fs);
        $result = $code->toArray();

        expect($result)->toHaveCount(7);
        expect(array_key_first($result))->toBe(7);
        expect(array_key_last($result))->toBe(13);
    });

});
