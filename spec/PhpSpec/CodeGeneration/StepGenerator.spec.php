<?php

use PhpSpec\CodeGeneration\StepGenerator;
use PhpSpec\Filesystem;

describe(StepGenerator::class, function () {

    let('written', fn() => new ArrayObject());
    let('filesystem', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturnUsing(function (string $path, string $content) {
            $this->written['content'] = $content;
        });

        return $fs;
    });

    it('generates given/when/then from the primary keywords', function () {
        (new StepGenerator($this->filesystem))->generate('/proj/features/x.feature', [
            ['keyword' => 'Given', 'text' => 'a precondition'],
            ['keyword' => 'When', 'text' => 'an action'],
            ['keyword' => 'Then', 'text' => 'an outcome'],
        ]);

        expect($this->written['content'])->toContain('given("a precondition"');
        expect($this->written['content'])->toContain('when("an action"');
        expect($this->written['content'])->toContain('then("an outcome"');
    });

    it('generates And and But as the keyword of the step they follow', function () {
        (new StepGenerator($this->filesystem))->generate('/proj/features/x.feature', [
            ['keyword' => 'Given', 'text' => 'a precondition'],
            ['keyword' => 'And', 'text' => 'another precondition'],
            ['keyword' => 'When', 'text' => 'an action'],
            ['keyword' => 'But', 'text' => 'not another action'],
            ['keyword' => 'Then', 'text' => 'an outcome'],
            ['keyword' => 'And', 'text' => 'another outcome'],
        ]);

        $content = $this->written['content'];
        expect($content)->toContain('given("another precondition"');
        expect($content)->toContain('when("not another action"');
        expect($content)->toContain('then("another outcome"');
        expect($content)->not()->toContain('given("not another action"');
        expect($content)->not()->toContain('given("another outcome"');
    });

    it('defaults a leading And with no preceding primary to given', function () {
        (new StepGenerator($this->filesystem))->generate('/proj/features/x.feature', [
            ['keyword' => 'And', 'text' => 'a stray step'],
        ]);

        expect($this->written['content'])->toContain('given("a stray step"');
    });
});
