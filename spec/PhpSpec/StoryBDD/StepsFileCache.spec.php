<?php

use PhpSpec\FileLoadException;
use PhpSpec\StoryBDD\StepMatch;
use PhpSpec\StoryBDD\StepsFileCache;
use PhpSpec\StoryBDD\StoryBDDRegistry;

describe(StepsFileCache::class, function () {

    this('file', fn() => sys_get_temp_dir() . '/phpspec_steps_' . uniqid() . '.steps.php');

    afterEach(function () {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    });

    it('requires a steps file once in a run, and hands every feature path what it registered', function () {
        $name = 'steps_cache_' . uniqid();
        file_put_contents($this->file, "<?php\nfunction {$name}(): string { return 'basket'; }\ngiven('a {$name}', function () {});\nbeforeScenario(function () {});\n");
        $running = StoryBDDRegistry::saveState();
        $run = new StepsFileCache();

        $first = $run->definitions($this->file);
        $second = $run->definitions($this->file);

        expect($second)->toBe($first);
        expect($first['steps']->match('a ' . $name))->toBeAnInstanceOf(StepMatch::class);
        expect(StoryBDDRegistry::saveState())->toBe($running);
    });

    it('leaves another run to require the file for itself', function () {
        file_put_contents($this->file, "<?php\ngiven('a basket', function () {});\n");

        expect((new StepsFileCache())->definitions($this->file))->not()->toBe((new StepsFileCache())->definitions($this->file));
    });

    it('stops on a steps file that cannot load, leaving the running registries as they were', function () {
        file_put_contents($this->file, "<?php\ngiven('a basket', function () {\n");
        $running = StoryBDDRegistry::saveState();

        expect(fn() => (new StepsFileCache())->definitions($this->file))->toThrow(FileLoadException::class);
        expect(StoryBDDRegistry::saveState())->toBe($running);
    });
});
