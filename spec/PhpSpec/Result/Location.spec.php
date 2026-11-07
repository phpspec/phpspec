<?php

use PhpSpec\ProjectRoot;
use PhpSpec\Result\Location;

describe(Location::class, function () {

    it('is nowhere without a file or a line to point at', function () {
        expect([Location::at(null, 3), Location::at('', 3), Location::at('a.php', null), Location::at('a.php', 0), Location::at('a.php', -1)])->toBe([null, null, null, null, null]);
    });

    it('reads as the file and the line', function () {
        expect((string) Location::at('spec/Calc.spec.php', 12))->toBe('spec/Calc.spec.php:12');
    });

    it('names its file from the project root, and one outside it as it is', function () {
        $root = ProjectRoot::at('/project');

        expect((string) Location::at('/project/spec/Calc.spec.php', 12)?->relativeTo($root))->toBe('spec/Calc.spec.php:12');
        expect((string) Location::at('/elsewhere/Tool.php', 3)?->relativeTo($root))->toBe('/elsewhere/Tool.php:3');
    });
});
