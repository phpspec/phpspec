<?php

use PhpSpec\CodeGeneration\StepsHome;
use PhpSpec\Configuration;
use PhpSpec\Filesystem;

describe(StepsHome::class, function () {

    let('tree', fn() => new ArrayObject([
        '/project/features' => ['steps', 'checkout.feature'],
        '/project/features/steps' => ['web.steps.php', 'steps.php', 'assertions.steps.php', 'helpers.php'],
    ]));
    let('filesystem', function (Filesystem $fs) {
        allow($fs->isDir())->toReturnUsing(fn(string $p): bool => isset($this->tree[$p]));
        allow($fs->exists())->toReturnUsing(fn(string $p): bool => isset($this->tree[$p]));
        allow($fs->scandir())->toReturnUsing(fn(string $p): array => $this->tree[$p] ?? []);

        return $fs;
    });

    it('keeps steps under the features path by default, and under steps_path when one is configured', function () {
        expect((new StepsHome(new Configuration(), $this->filesystem, '/project'))->directory())->toBe('features/steps');
        expect((new StepsHome(new Configuration(['features_path' => 'tests/features/']), $this->filesystem, '/project'))->directory())->toBe('tests/features/steps');
        expect((new StepsHome(new Configuration(['steps_path' => './acceptance_steps/']), $this->filesystem, '/project'))->directory())->toBe('acceptance_steps');
    });

    it('names steps.php the default steps file', function () {
        expect((new StepsHome(new Configuration(), $this->filesystem, '/project'))->defaultFile())->toBe('features/steps/steps.php');
        expect((new StepsHome(new Configuration(['steps_path' => 'acceptance_steps']), $this->filesystem, '/project'))->defaultFile())->toBe('acceptance_steps/steps.php');
    });

    it('turns a name into a steps file in the steps directory', function () {
        $home = new StepsHome(new Configuration(), $this->filesystem, '/project');

        expect($home->file('web'))->toBe('features/steps/web.steps.php');
        expect($home->file('web.steps.php'))->toBe('features/steps/web.steps.php');
        expect($home->file('web.php'))->toBe('features/steps/web.steps.php');
        expect($home->file(' steps '))->toBe('features/steps/steps.php');
        expect($home->file('steps.php'))->toBe('features/steps/steps.php');
    });

    it('refuses a name that is a path or no name at all', function () {
        $home = new StepsHome(new Configuration(), $this->filesystem, '/project');

        foreach (['', 'a/b', '../web', 'a\\b', 'web steps', '.steps.php'] as $name) {
            expect($home->file($name))->toBeNull();
        }
    });

    it('looks for steps under the features root, and under steps_path too when it lies elsewhere', function () {
        expect((new StepsHome(new Configuration(), $this->filesystem, '/project'))->roots())->toBe(['/project/features']);
        expect((new StepsHome(new Configuration(['steps_path' => 'acceptance_steps']), $this->filesystem, '/project'))->roots())->toBe(['/project/features', '/project/acceptance_steps']);
        expect((new StepsHome(new Configuration(['steps_path' => 'features/steps']), $this->filesystem, '/project'))->roots())->toBe(['/project/features']);
    });

    it('lists the steps files there are, the default first, each once', function () {
        $home = new StepsHome(new Configuration(['steps_path' => 'features/steps']), $this->filesystem, '/project');

        expect($home->files())->toBe(['features/steps/steps.php', 'features/steps/assertions.steps.php', 'features/steps/web.steps.php']);
    });

    it('lists no steps files when there are none', function () {
        $this->tree['/project/features/steps'] = ['helpers.php'];

        expect((new StepsHome(new Configuration(), $this->filesystem, '/project'))->files())->toBe([]);
    });

    it('labels a steps file by its name in the steps directory, and by its path elsewhere', function () {
        $home = new StepsHome(new Configuration(), $this->filesystem, '/project');

        expect($home->label('features/steps/web.steps.php'))->toBe('web.steps.php');
        expect($home->label('features/checkout/checkout.steps.php'))->toBe('features/checkout/checkout.steps.php');
    });
});
