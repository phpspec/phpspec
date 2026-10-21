<?php

use PhpSpec\Coverage\Exclusions;
use PhpSpec\Filesystem;

describe(Exclusions::class, function () {

    $clock = "<?php\nnamespace App;\nclass Clock\n{\n    /** @codeCoverageIgnore */\n    public function now(): int\n    {\n        return time();\n    }\n    public function today(): int\n    {\n        return 1;\n    }\n}\n";
    $pricing = "<?php\nnamespace App;\ninterface Pricing\n{\n    public function add(): int;\n}\n";

    $sources = function (Filesystem $fs, array $files, int &$reads = 0): void {
        allow($fs->exists(any()))->toReturnUsing(fn(string $path) => isset($files[$path]));
        allow($fs->read(any()))->toReturnUsing(function (string $path) use ($files, &$reads) {
            $reads++;

            return $files[$path];
        });
    };

    it('leaves out the lines a file marks, and a file with nothing left to cover', function (Filesystem $fs) use ($sources, $clock, $pricing) {
        $sources($fs, ['/app/src/Clock.php' => $clock, '/app/src/Pricing.php' => $pricing]);

        $kept = (new Exclusions([], $fs, '/app'))->appliedTo([
            '/app/src/Clock.php' => [8 => 0, 12 => 1, 14 => 1],
            '/app/src/Pricing.php' => [6 => 1],
        ]);

        expect($kept)->toBe(['/app/src/Clock.php' => [12 => 1, 14 => 1]]);
    });

    it('leaves out a file the config names by its path from the project root', function (Filesystem $fs) use ($sources, $clock) {
        $sources($fs, ['/app/src/Clock.php' => $clock, '/app/src/Generated/Mapper.php' => "<?php\nreturn 1;\n"]);

        $kept = (new Exclusions(['src/Generated/'], $fs, '/app'))->appliedTo([
            '/app/src/Generated/Mapper.php' => [2 => 1],
            '/app/src/Clock.php' => [12 => 1],
        ]);

        expect(array_keys($kept))->toBe(['/app/src/Clock.php']);
    });

    it('leaves out a declaration the config names, and a file left with no lines', function (Filesystem $fs) use ($sources, $clock) {
        $sources($fs, ['/app/src/Clock.php' => $clock]);
        $exclusions = new Exclusions(['App\Clock::today'], $fs, '/app');

        expect($exclusions->appliedTo(['/app/src/Clock.php' => [8 => 0, 12 => 1, 14 => 1]]))->toBe(['/app/src/Clock.php' => [14 => 1]]);
        expect($exclusions->appliedTo(['/app/src/Clock.php' => [8 => 0, 12 => 1]]))->toBe([]);
    });

    it('serves the per-test lines of the JSON report as it serves hits', function (Filesystem $fs) use ($sources, $clock) {
        $sources($fs, ['/app/src/Clock.php' => $clock]);

        $kept = (new Exclusions([], $fs, '/app'))->appliedTo(['/app/src/Clock.php' => [8 => ['t1'], 12 => ['t2']]]);

        expect($kept)->toBe(['/app/src/Clock.php' => [12 => ['t2']]]);
    });

    it('says whether a line is left out, the file named from the project root or in full', function (Filesystem $fs) use ($sources, $clock, $pricing) {
        $sources($fs, ['/app/src/Clock.php' => $clock, '/app/src/Pricing.php' => $pricing]);
        $exclusions = new Exclusions(['src/Generated/'], $fs, '/app');

        expect($exclusions->excludes('src/Clock.php', 8))->toBeTrue();
        expect($exclusions->excludes('/app/src/Clock.php', 12))->toBeFalse();
        expect($exclusions->excludes('src/Pricing.php', 6))->toBeTrue();
        expect($exclusions->excludes('src/Generated/Mapper.php', 1))->toBeTrue();
    });

    it('reads each file once, and leaves one it cannot read as it is', function (Filesystem $fs) use ($sources, $clock) {
        $reads = 0;
        $sources($fs, ['/app/src/Clock.php' => $clock], $reads);
        $exclusions = new Exclusions([], $fs, '/app');

        $exclusions->appliedTo(['/app/src/Clock.php' => [12 => 1]]);
        $exclusions->excludes('src/Clock.php', 12);

        expect($reads)->toBe(1);
        expect($exclusions->appliedTo(['/app/src/Gone.php' => [3 => 1]]))->toBe(['/app/src/Gone.php' => [3 => 1]]);
    });
});
