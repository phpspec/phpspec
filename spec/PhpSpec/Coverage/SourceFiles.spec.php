<?php

use PhpSpec\Coverage\Exclusions;
use PhpSpec\Coverage\SourceFiles;
use PhpSpec\Filesystem;
use PhpSpec\ProjectRoot;

describe(SourceFiles::class, function () {
    $tree = function (Filesystem $fs, array $files): void {
        $directories = [];

        foreach (array_keys($files) as $file) {
            for ($directory = dirname($file); $directory !== '/app'; $directory = dirname($directory)) {
                $directories[dirname($directory)][basename($directory)] = true;
            }

            $directories[dirname($file)][basename($file)] = true;
        }

        allow($fs->scandir(any()))->toReturnUsing(fn (string $path) => ['.', '..', ...array_keys($directories[$path] ?? [])]);
        allow($fs->isDir(any()))->toReturnUsing(fn (string $path) => isset($directories[$path]));
        allow($fs->exists(any()))->toReturnUsing(fn (string $path) => isset($files[$path]));
        allow($fs->read(any()))->toReturnUsing(fn (string $path) => $files[$path]);
    };

    $class = fn (string $name) => "<?php\nnamespace App;\nclass $name\n{\n    public function run(): int\n    {\n        return 1;\n    }\n}\n";

    it('names the PHP files under it with no coverage recorded, from the project root, at any depth', function (Filesystem $fs) use ($tree, $class) {
        $tree($fs, [
            '/app/src/Greeter.php' => $class('Greeter'),
            '/app/src/Untested.php' => $class('Untested'),
            '/app/src/Reports/Monthly.php' => $class('Monthly'),
            '/app/src/README.md' => 'notes',
        ]);

        $unrecorded = (new SourceFiles($fs, ['/app/src'], ProjectRoot::at('/app')))->unrecorded(['/app/src/Greeter.php' => [7 => 1]], new Exclusions([], $fs, '/app'));

        expect($unrecorded)->toBe(['src/Reports/Monthly.php', 'src/Untested.php']);
    });

    it('names them under every source directory it is given', function (Filesystem $fs) use ($tree, $class) {
        $tree($fs, [
            '/app/src/Greeter.php' => $class('Greeter'),
            '/app/lib/Ledger.php' => $class('Ledger'),
        ]);

        $unrecorded = (new SourceFiles($fs, ['/app/src', '/app/lib'], ProjectRoot::at('/app')))->unrecorded([], new Exclusions([], $fs, '/app'));

        expect($unrecorded)->toBe(['lib/Ledger.php', 'src/Greeter.php']);
    });

    it('leaves out a file coverage leaves out, by the config or for having nothing to cover', function (Filesystem $fs) use ($tree, $class) {
        $tree($fs, [
            '/app/src/Generated/Mapper.php' => $class('Mapper'),
            '/app/src/Pricing.php' => "<?php\nnamespace App;\ninterface Pricing\n{\n    public function price(): int;\n}\n",
        ]);

        $unrecorded = (new SourceFiles($fs, ['/app/src'], ProjectRoot::at('/app')))->unrecorded([], new Exclusions(['src/Generated/'], $fs, '/app'));

        expect($unrecorded)->toBe([]);
    });

    it('knows a file with coverage recorded however the engine spelled its path', function (Filesystem $fs) use ($tree, $class) {
        $tree($fs, ['/app/src/Greeter.php' => $class('Greeter')]);

        $unrecorded = (new SourceFiles($fs, ['/app/src'], ProjectRoot::at('/app')))->unrecorded(['\\app\\src\\Greeter.php' => [7 => 1]], new Exclusions([], $fs, '/app'));

        expect($unrecorded)->toBe([]);
    });
});
