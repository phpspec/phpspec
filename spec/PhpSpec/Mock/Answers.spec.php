<?php

use PhpSpec\CodeGeneration\SourceLayout;
use PhpSpec\Mock\Answers;
use PhpSpec\Source\ProjectAutoloader;

describe(Answers::class, function () {
    this('project', fn () => function (array $files): array {
        $namespace = 'AnswerFx' . bin2hex(random_bytes(4));
        $root = sys_get_temp_dir() . '/phpspec_answers_' . $namespace;
        mkdir($root . '/src', 0777, true);

        foreach ($files as $path => $code) {
            file_put_contents($root . '/src/' . $path, str_replace('NS', $namespace, $code));
        }

        $loader = new ProjectAutoloader(new SourceLayout('src', [$namespace => 'src']), $root);
        spl_autoload_register($loader);
        Answers::reset();

        return [$namespace, $root, $loader];
    });

    this('cleanUp', fn () => function (string $root, ProjectAutoloader $loader): void {
        spl_autoload_unregister($loader);
        Answers::reset();
        array_map('unlink', glob($root . '/src/*.php') ?: []);
        rmdir($root . '/src');
        rmdir($root);
    });

    it('looks for an enum again once a class or an interface loads after it found none', function () {
        [$namespace, $root, $loader] = ($this->project)([
            'Labelled.php' => "<?php\nnamespace NS;\ninterface Labelled extends \\UnitEnum {}\n",
            'Status.php' => "<?php\nnamespace NS;\nenum Status implements Child { case Sent; }\n",
        ]);

        try {
            expect(Answers::enumCase($namespace . '\\Labelled'))->toBeNull();

            eval('namespace ' . $namespace . '; interface Child extends Labelled {}');

            expect(Answers::enumCase($namespace . '\\Labelled'))->toBe(constant($namespace . '\\Status::Sent'));

            eval('namespace ' . $namespace . '; enum Aaa implements Labelled { case Earlier; }');

            expect(Answers::enumCase($namespace . '\\Labelled'))->toBe(constant($namespace . '\\Status::Sent'));

            eval('namespace ' . $namespace . '; interface Other extends \\UnitEnum {}');
            expect(Answers::enumCase($namespace . '\\Other'))->toBeNull();

            eval('namespace ' . $namespace . '; enum Late implements Other { case One; }');

            expect(Answers::enumCase($namespace . '\\Other'))->toBe(constant($namespace . '\\Late::One'));
        } finally {
            ($this->cleanUp)($root, $loader);
        }
    });

    it('reads the source afresh once reset, as a run in the same process starts', function () {
        [$namespace, $root, $loader] = ($this->project)([
            'Labelled.php' => "<?php\nnamespace NS;\ninterface Labelled extends \\UnitEnum {}\n",
            'Status.php' => "<?php\nnamespace NS;\nenum Status { case Sent; }\n",
        ]);

        try {
            expect(Answers::enumCase($namespace . '\\Labelled'))->toBeNull();

            file_put_contents($root . '/src/Status.php', "<?php\nnamespace {$namespace};\nenum Status implements Labelled { case Sent; }\n");
            eval('namespace ' . $namespace . '; interface Unrelated {}');

            expect(Answers::enumCase($namespace . '\\Labelled'))->toBeNull();

            Answers::reset();

            expect(Answers::enumCase($namespace . '\\Labelled'))->toBe(constant($namespace . '\\Status::Sent'));
        } finally {
            ($this->cleanUp)($root, $loader);
        }
    });
});
