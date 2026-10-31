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

    it('loads a class from the autoload map first, then from where the layout puts it', function () {
        $namespace = 'LoadFx' . bin2hex(random_bytes(4));
        $root = ($this->project)([
            'mapped/Mapped.php' => "<?php\nnamespace {$namespace};\nfinal class Mapped {}\n",
            'mapped/Both.php' => "<?php\nnamespace {$namespace};\nfinal class Both { const FROM = 'map'; }\n",
            'src/Both.php' => "<?php\nnamespace {$namespace};\nfinal class Both { const FROM = 'layout'; }\n",
            'src/Laid.php' => "<?php\nnamespace {$namespace};\nfinal class Laid {}\n",
        ]);
        $loader = new ProjectAutoloader([$namespace . '\\' => $root . '/mapped'], new SourceLayout('src', [$namespace => 'src']), $root);

        try {
            $loader($namespace . '\\Mapped');
            $loader($namespace . '\\Both');
            $loader($namespace . '\\Laid');
            $loader($namespace . '\\Missing');

            expect(class_exists($namespace . '\\Mapped', false))->toBeTrue();
            expect(constant($namespace . '\\Both::FROM'))->toBe('map');
            expect(class_exists($namespace . '\\Laid', false))->toBeTrue();
            expect(class_exists($namespace . '\\Missing', false))->toBeFalse();
        } finally {
            ($this->cleanUp)($root);
        }
    });

    it('names the directories it loads from: the autoload map\'s, the source path and each mapped one, under the project', function () {
        $loader = new ProjectAutoloader(['Extra\\' => '/elsewhere/extra'], new SourceLayout('src', ['Domain' => 'domain']), '/project');

        expect($loader->directories())->toBe(['/elsewhere/extra', '/project/src', '/project/domain']);
    });
});
