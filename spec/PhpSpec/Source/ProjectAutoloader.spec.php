<?php

use PhpSpec\CodeGeneration\SourceLayout;
use PhpSpec\Source\ProjectAutoloader;

describe(ProjectAutoloader::class, function () {
    this('project', fn () => function (array $files): string {
        $root = sys_get_temp_dir() . '/phpspec_autoload_' . bin2hex(random_bytes(4));

        foreach ($files as $path => $code) {
            if (!is_dir(dirname($root . '/' . $path))) {
                mkdir(dirname($root . '/' . $path), 0777, true);
            }

            file_put_contents($root . '/' . $path, $code);
        }

        return $root;
    });

    this('cleanUp', fn () => function (string $root): void {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($root);
    });

    it('loads a class from where the layout puts it: the directory its namespace is mapped to, else its whole name below the source path', function () {
        $namespace = 'LoadFx' . bin2hex(random_bytes(4));
        $root = ($this->project)([
            'mapped/Mapped.php' => "<?php\nnamespace {$namespace};\nfinal class Mapped {}\n",
            "src/{$namespace}Free/Laid.php" => "<?php\nnamespace {$namespace}Free;\nfinal class Laid {}\n",
        ]);
        $loader = new ProjectAutoloader(new SourceLayout('src', [$namespace => 'mapped']), $root);

        try {
            $loader($namespace . '\\Mapped');
            $loader($namespace . 'Free\\Laid');
            $loader($namespace . '\\Missing');

            expect(class_exists($namespace . '\\Mapped', false))->toBeTrue();
            expect(class_exists($namespace . 'Free\\Laid', false))->toBeTrue();
            expect(class_exists($namespace . '\\Missing', false))->toBeFalse();
        } finally {
            ($this->cleanUp)($root);
        }
    });

    it('leaves a class under a mapping unloaded when it is filed anywhere but where the mapping puts it', function () {
        $namespace = 'LoadFx' . bin2hex(random_bytes(4));
        $root = ($this->project)([
            "src/{$namespace}/Misfiled.php" => "<?php\nnamespace {$namespace};\nfinal class Misfiled {}\n",
        ]);
        $loader = new ProjectAutoloader(new SourceLayout('src', [$namespace => 'src']), $root);

        try {
            $loader($namespace . '\\Misfiled');

            expect(class_exists($namespace . '\\Misfiled', false))->toBeFalse();
        } finally {
            ($this->cleanUp)($root);
        }
    });

    it('names the directories it loads from: the source path and each mapped one, under the project', function () {
        $loader = new ProjectAutoloader(new SourceLayout('src', ['Domain' => 'domain']), '/project');

        expect($loader->directories())->toBe(['/project/src', '/project/domain']);
    });
});
