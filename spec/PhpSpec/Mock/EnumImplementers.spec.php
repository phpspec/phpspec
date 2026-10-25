<?php

use PhpSpec\Mock\EnumImplementers;

describe(EnumImplementers::class, function () {
    this('project', fn () => function (array $files): array {
        $namespace = 'EnumFx' . bin2hex(random_bytes(4));
        $root = sys_get_temp_dir() . '/phpspec_enums_' . $namespace;

        foreach ($files as $path => $code) {
            $file = $root . '/src/' . $namespace . '/' . $path;

            if (!is_dir(dirname($file))) {
                mkdir(dirname($file), 0777, true);
            }

            file_put_contents($file, "<?php\nnamespace " . $namespace . str_replace('NS', $namespace, $code));
        }

        $loader = static function (string $class) use ($root): void {
            $file = $root . '/src/' . str_replace('\\', '/', $class) . '.php';

            if (is_file($file)) {
                require $file;
            }
        };
        spl_autoload_register($loader);

        return [$namespace, $root, $loader];
    });

    this('cleanUp', fn () => function (string $root, Closure $loader): void {
        spl_autoload_unregister($loader);
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($root);
    });

    it('finds the first case of the first enum, by name, implementing the interface under its namespace, loading only what declares one', function () {
        [$namespace, $root, $loader] = ($this->project)([
            'Contracts/Labelled.php' => "\\Contracts;\ninterface Labelled extends \\UnitEnum {}\n",
            'Other/Labelled.php' => "\\Other;\ninterface Labelled {}\n",
            'Archive/Aardvark.php' => "\\Archive;\nenum Aardvark implements \\NS\\Other\\Labelled { case Odd; }\n",
            'Status.php' => ";\nuse NS\\Contracts\\Labelled as Tag;\nenum Status: string implements Tag { case Sent = 'sent'; case Draft = 'draft'; }\n",
            'Reader.php' => ";\nuse NS\\Contracts\\Labelled;\nfinal class Reader { public function read(Labelled \$label): void {} }\n",
            'Unrelated.php' => ";\nenum Unrelated { case None; }\n",
            'Stranger.php' => ";\nenum Stranger implements \\Countable { case One; public function count(): int { return 1; } }\n",
            'Broken.php' => ";\nenum Broken implements Contracts\\Labelled { case\n",
        ]);

        try {
            $case = (new EnumImplementers())->firstCaseOf($namespace . '\\Contracts\\Labelled');

            expect($case)->toBe(constant($namespace . '\\Status::Sent'));
            expect(class_exists($namespace . '\\Reader', false))->toBeFalse();
            expect(enum_exists($namespace . '\\Unrelated', false))->toBeFalse();
            expect(enum_exists($namespace . '\\Stranger', false))->toBeFalse();
        } finally {
            ($this->cleanUp)($root, $loader);
        }
    });

    it('finds only one already loaded for an interface with no source to read', function () {
        $namespace = 'EnumFx' . bin2hex(random_bytes(4));
        eval('namespace ' . $namespace . '; interface Labelled extends \\UnitEnum {} enum Shade implements Labelled { case Pale; }');

        expect((new EnumImplementers())->firstCaseOf($namespace . '\\Labelled'))->toBe(constant($namespace . '\\Shade::Pale'));
    });

    it('finds one already loaded, wherever it was declared', function () {
        [$namespace, $root, $loader] = ($this->project)([
            'Labelled.php' => ";\ninterface Labelled extends \\UnitEnum {}\n",
        ]);

        try {
            eval('namespace Elsewhere' . $namespace . '; enum Shade implements \\' . $namespace . '\\Labelled { case Pale; }');

            expect((new EnumImplementers())->firstCaseOf($namespace . '\\Labelled'))->toBe(constant('Elsewhere' . $namespace . '\\Shade::Pale'));
        } finally {
            ($this->cleanUp)($root, $loader);
        }
    });

    it('finds none where no enum with a case implements it, and searches no directory it is told to leave out', function () {
        [$namespace, $root, $loader] = ($this->project)([
            'Labelled.php' => ";\ninterface Labelled extends \\UnitEnum {}\n",
            'Lib/Hidden.php' => "\\Lib;\nenum Hidden implements \\NS\\Labelled { case Kept; }\n",
            'Hollow.php' => ";\nenum Hollow implements Labelled {}\n",
        ]);

        try {
            expect((new EnumImplementers([$root . '/src/' . $namespace . '/Lib']))->firstCaseOf($namespace . '\\Labelled'))->toBeNull();
            expect(enum_exists($namespace . '\\Lib\\Hidden', false))->toBeFalse();
        } finally {
            ($this->cleanUp)($root, $loader);
        }
    });
});
