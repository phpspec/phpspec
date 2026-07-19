<?php

use PhpSpec\Configuration;
use PhpSpec\Console\Command\Generate\GenerateAgent;
use PhpSpec\Filesystem;

describe(GenerateAgent::class, function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
        allow($fs->scandir())->toReturn([]);
    });

    let('config', fn(Filesystem $fs) => new Configuration('.', $fs));
    let('aiConfig', fn() => ['provider' => 'openai', 'api_key' => 'test-key']);

    it('proposes a new file from an instruction', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        $fn = fn(array $ai, string $context) => json_encode(['path' => 'src/App/Calc.php', 'content' => "<?php\nclass Calc {}"]);
        $agent = new GenerateAgent($this->config, $fs, $fn);

        $proposal = $agent->propose($this->aiConfig, 'a Calc class');

        expect($proposal['path'])->toBe('src/App/Calc.php');
        expect($proposal['new'])->toContain('class Calc');
        expect($proposal['isNew'])->toBe(true);
        expect($proposal['old'])->toBe('');
    });

    it('carries the existing content for a modified file', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n// old");
        $fn = fn() => json_encode(['path' => 'src/App/Calc.php', 'content' => "<?php\n// new"]);
        $agent = new GenerateAgent($this->config, $fs, $fn);

        $proposal = $agent->propose($this->aiConfig, 'change Calc');

        expect($proposal['isNew'])->toBe(false);
        expect($proposal['old'])->toBe("<?php\n// old");
        expect($proposal['new'])->toBe("<?php\n// new");
    });

    it('returns null when the AI produces nothing usable', function (Filesystem $fs) {
        $agent = new GenerateAgent($this->config, $fs, fn() => null);

        expect($agent->propose($this->aiConfig, 'x'))->toBeNull();
    });

    it('returns null when the AI reply is not valid JSON', function (Filesystem $fs) {
        $agent = new GenerateAgent($this->config, $fs, fn() => 'sorry, I could not do that');

        expect($agent->propose($this->aiConfig, 'x'))->toBeNull();
    });

    it('rejects a spec edit that would drop an example', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn('<?php describe("X", function () { it("a", fn() => null); it("b", fn() => null); });');
        $fn = fn() => json_encode(['path' => 'spec/App/X.spec.php', 'content' => '<?php describe("X", function () { it("a", fn() => null); });']);
        $agent = new GenerateAgent($this->config, $fs, $fn);

        expect($agent->propose($this->aiConfig, 'rewrite'))->toBeNull();
    });

    it('allows a spec edit that keeps the example count', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn('<?php describe("X", function () { it("a", fn() => null); });');
        $fn = fn() => json_encode(['path' => 'spec/App/X.spec.php', 'content' => '<?php describe("X", function () { it("returns zero", fn() => expect(1)->toBe(0)); });']);
        $agent = new GenerateAgent($this->config, $fs, $fn);

        $proposal = $agent->propose($this->aiConfig, 'rewrite');

        expect($proposal)->not()->toBeNull();
        expect($proposal['new'])->toContain('returns zero');
    });

    it('feeds the AI the project tree and the files the instruction names', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $p) => str_ends_with($p, 'src') || str_ends_with($p, 'spec') || str_ends_with($p, 'Calc.php') || str_ends_with($p, 'Calc.spec.php'));
        allow($fs->isDir())->toReturnUsing(fn(string $p) => str_ends_with($p, 'src') || str_ends_with($p, 'spec'));
        allow($fs->scandir())->toReturn(['Calc.php']);
        allow($fs->read())->toReturn('<?php class Calc {}');

        $captured = '';
        $chatFn = function (array $ai, string $context) use (&$captured) {
            $captured = $context;

            return json_encode(['path' => 'src/Calc.php', 'content' => '<?php // updated']);
        };
        $agent = new GenerateAgent($this->config, $fs, $chatFn);

        $agent->propose($this->aiConfig, 'change Calc to do something');

        expect($captured)->toContain('change Calc to do something');
        expect($captured)->toContain('Calc.php');
    });

    it('writes the proposal content to the filesystem', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        $written = [];
        allow($fs->write())->toReturnUsing(function (string $p, string $c) use (&$written) {
            $written[$p] = $c;
        });
        $agent = new GenerateAgent($this->config, $fs);

        $agent->write(['path' => 'src/App/Calc.php', 'old' => '', 'new' => "<?php\nclass Calc {}", 'isNew' => false]);

        expect($written)->toHaveLength(1);
        expect(array_values($written)[0])->toContain('class Calc');
    });

});
