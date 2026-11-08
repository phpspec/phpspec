<?php

use PhpSpec\InProcessSymbols;
use PhpSpec\TemporaryDirectory;

describe(InProcessSymbols::class, function () {

    $made = [];

    afterAll(function () use (&$made) {
        foreach ($made as $directory) {
            $directory->remove();
        }
    });

    this('project', function () use (&$made) {
        return function (array $files) use (&$made): string {
            $directory = TemporaryDirectory::create('phpspec_symbols_spec_');
            $made[] = $directory;

            foreach ($files as $path => $content) {
                if (!is_dir(dirname($directory->path . '/' . $path))) {
                    mkdir(dirname($directory->path . '/' . $path), 0777, true);
                }
                file_put_contents($directory->path . '/' . $path, $content);
            }

            return $directory->path;
        };
    });

    this('unique', fn() => fn(string $name): string => $name . bin2hex(random_bytes(6)));

    this('declaring', fn() => function (string $class): string {
        [$namespace, $short] = explode('\\', $class);

        return "<?php\nnamespace {$namespace};\nclass {$short} {}\n";
    });

    it('lets a project that names nothing another project declared run in process', function () {
        $class = ($this->unique)('Shop') . '\\Basket';
        $symbols = new InProcessSymbols();
        $first = ($this->project)(['src/Basket.php' => ($this->declaring)($class)]);
        $symbols->record($first, fn() => require $first . '/src/Basket.php');

        expect($symbols->conflictIn(($this->project)(['spec/Other.spec.php' => '<?php new Other\Thing();']), 'run'))->toBeNull();
    });

    it('refuses a project that names a class another project declared, by its name, by a path or by its short name in its namespace', function () {
        $namespace = ($this->unique)('Shop');
        $symbols = new InProcessSymbols();
        $first = ($this->project)(['src/Basket.php' => ($this->declaring)("{$namespace}\\Basket")]);
        $symbols->record($first, fn() => require $first . '/src/Basket.php');

        expect($symbols->conflictIn(($this->project)(['spec/Basket.spec.php' => "<?php expect({$namespace}\\Basket::class);"]), 'run'))->toContain("{$namespace}\\Basket");
        expect($symbols->conflictIn(($this->project)(['spec/Basket.spec.php' => "<?php mock('{$namespace}\\\\Basket');"]), 'run'))->not()->toBeNull();
        expect($symbols->conflictIn(($this->project)([]), "describe {$namespace}/Basket"))->not()->toBeNull();
        expect($symbols->conflictIn(($this->project)(['src/Till.php' => "<?php\nnamespace {$namespace};\nclass Till { public function take(Basket \$basket) {} }\n"]), 'run'))->not()->toBeNull();
    });

    it('leaves alone a short name in another namespace, or one that names a variable, a property or a method', function () {
        $namespace = ($this->unique)('Shop');
        $symbols = new InProcessSymbols();
        $first = ($this->project)(['src/Basket.php' => ($this->declaring)("{$namespace}\\Basket")]);
        $symbols->record($first, fn() => require $first . '/src/Basket.php');

        expect($symbols->conflictIn(($this->project)(['src/Till.php' => "<?php\nnamespace Other;\nclass Till { public function take(Basket \$basket) { return \$basket->basket; } }\n"]), 'run'))->toBeNull();
    });

    it('refuses a project whose own class changed since a run declared it, and lets it run again while it is unchanged', function () {
        $class = ($this->unique)('Shop') . '\\Basket';
        $symbols = new InProcessSymbols();
        $project = ($this->project)(['src/Basket.php' => ($this->declaring)($class)]);
        $symbols->record($project, fn() => require $project . '/src/Basket.php');

        expect($symbols->conflictIn($project, 'run'))->toBeNull();

        file_put_contents($project . '/src/Basket.php', "<?php\n// changed\n", FILE_APPEND);

        expect($symbols->conflictIn($project, 'run'))->toContain($class);
    });

    it('refuses a project whose class was written while the run that declared it ran, as code generation does', function () {
        $class = ($this->unique)('Shop') . '\\Basket';
        $symbols = new InProcessSymbols();
        $project = ($this->project)([]);
        $symbols->record($project, function () use ($project, $class) {
            mkdir($project . '/src');
            file_put_contents($project . '/src/Basket.php', ($this->declaring)($class));
            require $project . '/src/Basket.php';
        });

        expect($symbols->conflictIn($project, 'run'))->toContain($class);
    });

    it('refuses a project that names a function another project declared', function () {
        $function = ($this->unique)('greeting_');
        $symbols = new InProcessSymbols();
        $first = ($this->project)(['bootstrap.php' => "<?php\nfunction {$function}(): string { return 'first'; }\n"]);
        $symbols->record($first, fn() => require $first . '/bootstrap.php');

        expect($symbols->conflictIn(($this->project)(['spec/Greeting.spec.php' => "<?php expect(function_exists('{$function}'));"]), 'run'))->toContain($function);
    });

    it('keeps no record of what phpspec and its dependencies declare, which every run shares', function () {
        $class = ($this->unique)('Shop') . '\\Basket';
        $first = ($this->project)(['src/Basket.php' => ($this->declaring)($class)]);
        $symbols = new InProcessSymbols($first);
        $symbols->record($first, fn() => require $first . '/src/Basket.php');

        expect($symbols->conflictIn(($this->project)(['spec/Basket.spec.php' => "<?php expect({$class}::class);"]), 'run'))->toBeNull();
    });

    it('answers what the run answered', function () {
        expect((new InProcessSymbols())->record(($this->project)([]), fn() => 7))->toBe(7);
    });
});
