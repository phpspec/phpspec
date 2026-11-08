<?php

use Composer\Autoload\ClassLoader;
use PhpSpec\CodeGeneration\SourceLayout;
use PhpSpec\Mock\EnumImplementers;
use PhpSpec\OwnCode;
use PhpSpec\ProjectRoot;
use PhpSpec\Source\ProjectAutoloader;

describe(EnumImplementers::class, function () {
    this('project', fn () => function (array $files, array $directories = ['' => 'src/']): array {
        $namespace = 'EnumFx' . bin2hex(random_bytes(4));
        $root = sys_get_temp_dir() . '/phpspec_enums_' . $namespace;

        foreach ($files as $path => $code) {
            $file = $root . '/' . str_replace('NS', $namespace, $path);

            if (!is_dir(dirname($file))) {
                mkdir(dirname($file), 0777, true);
            }

            file_put_contents($file, str_replace('NS', $namespace, $code));
        }

        $loader = static function (string $class) use ($root, $namespace, $directories): void {
            foreach ($directories as $suffix => $directory) {
                $prefix = $namespace . $suffix . '\\';
                $file = $root . '/' . $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

                if (str_starts_with($class, $prefix) && is_file($file)) {
                    require $file;

                    return;
                }
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

    it('finds the first case of the first enum, by name, implementing the interface or one extending it, loading only what could be one', function () {
        [$namespace, $root, $loader] = ($this->project)([
            'src/Contracts/Labelled.php' => "<?php\nnamespace NS\\Contracts;\ninterface Labelled extends \\UnitEnum {}\n",
            'src/Contracts/Child.php' => "<?php\nnamespace NS\\Contracts;\ninterface Child extends Labelled {}\n",
            'src/Contracts/Grandchild.php' => "<?php\nnamespace NS\\Contracts;\nuse NS\\Contracts\\Child as Base;\ninterface Grandchild extends Base {}\n",
            'src/Other/Labelled.php' => "<?php\nnamespace NS\\Other;\ninterface Labelled {}\n",
            'src/Archive/Aardvark.php' => "<?php\nnamespace NS\\Archive;\nenum Aardvark implements \\NS\\Other\\Labelled { case Odd; }\n",
            'src/Broken.php' => "<?php\nnamespace NS;\nenum Broken implements Contracts\\Child { case\n",
            'src/Status.php' => "<?php\nnamespace NS;\nuse NS\\Contracts\\Grandchild;\nenum Status: string implements Grandchild { case Sent = 'sent'; case Draft = 'draft'; }\n",
            'src/Reader.php' => "<?php\nnamespace NS;\nuse NS\\Contracts\\Labelled;\nfinal class Reader { public function read(Labelled \$label): void {} }\n",
            'src/Unrelated.php' => "<?php\nnamespace NS;\nenum Unrelated { case None; }\n",
            'src/Stranger.php' => "<?php\nnamespace NS;\nenum Stranger implements \\Countable { case One; public function count(): int { return 1; } }\n",
        ]);

        try {
            expect((new EnumImplementers())->firstCaseOf($namespace . '\\Contracts\\Labelled'))->toBe(constant($namespace . '\\Status::Sent'));

            foreach (['Archive\\Aardvark', 'Reader', 'Unrelated', 'Stranger'] as $unloaded) {
                expect(class_exists($namespace . '\\' . $unloaded, false))->toBeFalse();
            }
        } finally {
            ($this->cleanUp)($root, $loader);
        }
    });

    it('finds one in another directory it is given, outside the interface\'s namespace', function () {
        [$namespace, $root, $loader] = ($this->project)([
            'contracts/Labelled.php' => "<?php\nnamespace NS;\ninterface Labelled extends \\UnitEnum {}\n",
            'domain/Status.php' => "<?php\nnamespace NSDomain;\nenum Status implements \\NS\\Labelled { case Sent; }\n",
        ], ['' => 'contracts/', 'Domain' => 'domain/']);

        try {
            expect((new EnumImplementers())->firstCaseOf($namespace . '\\Labelled'))->toBeNull();
            expect((new EnumImplementers([$root . '/missing', $root . '/domain']))->firstCaseOf($namespace . '\\Labelled'))->toBe(constant($namespace . 'Domain\\Status::Sent'));
        } finally {
            ($this->cleanUp)($root, $loader);
        }
    });

    it('searches what the autoloaders load the project\'s own classes from, Composer\'s and the configured ones, and no library', function () {
        [$namespace, $root, $loader] = ($this->project)([
            'contracts/Labelled.php' => "<?php\nnamespace NS;\ninterface Labelled extends \\UnitEnum {}\n",
            'domain/Zeta.php' => "<?php\nnamespace NSDomain;\nenum Zeta implements \\NS\\Labelled { case Last; }\n",
            'alt/Alpha.php' => "<?php\nnamespace NSAlt;\nenum Alpha implements \\NS\\Labelled { case First; }\n",
            'vendor/lib/Aaron.php' => "<?php\nnamespace NSLib;\nenum Aaron implements \\NS\\Labelled { case Library; }\n",
        ], ['' => 'contracts/', 'Domain' => 'domain/', 'Alt' => 'alt/', 'Lib' => 'vendor/lib/']);

        try {
            $composer = new ClassLoader($root . '/vendor');
            $composer->addPsr4($namespace . 'Domain\\', $root . '/domain');
            $composer->addPsr4($namespace . 'Lib\\', $root . '/vendor/lib');
            $configured = new ProjectAutoloader(new SourceLayout('contracts', [$namespace . 'Alt' => 'alt']), $root);
            $own = new OwnCode(ProjectRoot::at($root), [ProjectRoot::at($root . '/vendor')]);

            expect(EnumImplementers::of([[$composer, 'loadClass']], $own)->firstCaseOf($namespace . '\\Labelled'))->toBe(constant($namespace . 'Domain\\Zeta::Last'));
            expect(EnumImplementers::of([[$composer, 'loadClass'], $configured], $own)->firstCaseOf($namespace . '\\Labelled'))->toBe(constant($namespace . 'Alt\\Alpha::First'));
            expect(enum_exists($namespace . 'Lib\\Aaron', false))->toBeFalse();
        } finally {
            ($this->cleanUp)($root, $loader);
        }
    });

    it('searches the project root when Composer falls back to it, and still no library under it', function () {
        [$namespace, $root, $loader] = ($this->project)([
            'contracts/Labelled.php' => "<?php\nnamespace NS;\ninterface Labelled extends \\UnitEnum {}\n",
            'NSDomain/Status.php' => "<?php\nnamespace NSDomain;\nenum Status implements \\NS\\Labelled { case Sent; }\n",
            'vendor/lib/Aaron.php' => "<?php\nnamespace NSLib;\nenum Aaron implements \\NS\\Labelled { case Library; }\n",
        ], ['' => 'contracts/', 'Lib' => 'vendor/lib/']);
        $composer = new ClassLoader($root . '/vendor');
        $composer->addPsr4($namespace . '\\', $root . '/contracts');
        $composer->addPsr4($namespace . 'Lib\\', $root . '/vendor/lib');
        $composer->addPsr4('', $root);
        $composer->register();

        try {
            $own = new OwnCode(ProjectRoot::at($root), [ProjectRoot::at($root . '/vendor')]);

            expect(EnumImplementers::of([[$composer, 'loadClass']], $own)->firstCaseOf($namespace . '\\Labelled'))->toBe(constant($namespace . 'Domain\\Status::Sent'));
            expect(enum_exists($namespace . 'Lib\\Aaron', false))->toBeFalse();
        } finally {
            $composer->unregister();
            ($this->cleanUp)($root, $loader);
        }
    });

    it('searches no directory outside the project, whatever an autoloader names', function () {
        [$namespace, $root, $loader] = ($this->project)([
            'project/contracts/Labelled.php' => "<?php\nnamespace NS;\ninterface Labelled extends \\UnitEnum {}\n",
            'outside/Status.php' => "<?php\nnamespace NSOut;\nenum Status implements \\NS\\Labelled { case Sent; }\n",
        ], ['' => 'project/contracts/', 'Out' => 'outside/']);

        try {
            $configured = new ProjectAutoloader(new SourceLayout('contracts', [$namespace . 'Out' => '../outside']), $root . '/project');
            $own = new OwnCode(ProjectRoot::at($root . '/project'), []);

            expect(EnumImplementers::of([$configured], $own)->firstCaseOf($namespace . '\\Labelled'))->toBeNull();
            expect(enum_exists($namespace . 'Out\\Status', false))->toBeFalse();
        } finally {
            ($this->cleanUp)($root, $loader);
        }
    });

    it('steps over a directory it cannot read, the one it starts at too', function () {
        if (DIRECTORY_SEPARATOR === '\\' || (function_exists('posix_getuid') && posix_getuid() === 0)) {
            skip('File modes do not stop this user reading.');
        }

        [$namespace, $root, $loader] = ($this->project)([
            'src/Labelled.php' => "<?php\nnamespace NS;\ninterface Labelled extends \\UnitEnum {}\n",
            'src/Status.php' => "<?php\nnamespace NS;\nenum Status implements Labelled { case Sent; }\n",
            'src/Locked/Kept.php' => "<?php\nnamespace NS\\Locked;\nenum Kept implements \\NS\\Labelled { case Hidden; }\n",
        ]);
        chmod($root . '/src/Locked', 0);

        try {
            expect((new EnumImplementers())->firstCaseOf($namespace . '\\Labelled'))->toBe(constant($namespace . '\\Status::Sent'));
            expect((new EnumImplementers([$root . '/src/Locked']))->firstCaseOf($namespace . '\\Labelled'))->toBe(constant($namespace . '\\Status::Sent'));
        } finally {
            chmod($root . '/src/Locked', 0o755);
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
            'src/Labelled.php' => "<?php\nnamespace NS;\ninterface Labelled extends \\UnitEnum {}\n",
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
            'src/Labelled.php' => "<?php\nnamespace NS;\ninterface Labelled extends \\UnitEnum {}\n",
            'src/Lib/Hidden.php' => "<?php\nnamespace NS\\Lib;\nenum Hidden implements \\NS\\Labelled { case Kept; }\n",
            'src/Hollow.php' => "<?php\nnamespace NS;\nenum Hollow implements Labelled {}\n",
        ]);

        try {
            expect((new EnumImplementers([], [$root . '/src/Lib']))->firstCaseOf($namespace . '\\Labelled'))->toBeNull();
            expect(enum_exists($namespace . '\\Lib\\Hidden', false))->toBeFalse();
        } finally {
            ($this->cleanUp)($root, $loader);
        }
    });
});
