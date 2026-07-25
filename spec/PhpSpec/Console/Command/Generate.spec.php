<?php

use PhpSpec\Ai\Response;
use PhpSpec\Ai\ToolCall;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Generate;
use PhpSpec\Filesystem;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/../../Ai/ReplayProvider.php';

describe(Generate::class, function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
        allow($fs->scandir())->toReturn([]);
        allow($fs->read())->toReturn('');
        allow($fs->mkdir())->toReturn(null);
    });

    $withAi = function (Filesystem $fs): void {
        $yamlPath = './phpspec.yaml';
        allow($fs->exists())->toReturnUsing(fn(string $p) => $p === $yamlPath);
        allow($fs->read())->toReturnUsing(fn(string $p) => $p === $yamlPath
            ? "ai:\n  provider: openai\n  api_key: test-key\n"
            : '');
    };

    it('errors without AI configuration', function (Filesystem $fs) {
        $cmd = new Generate(new Configuration('.', $fs));
        $tester = new CommandTester($cmd);

        $tester->execute(['instruction' => ['a', 'Calc']], ['interactive' => false]);

        expect($tester->getDisplay())->toContain('AI configuration required');
    });

    it('shows a NEW FILE diff and writes the proposal non-interactively', function (Filesystem $fs) use ($withAi) {
        $withAi($fs);
        $written = [];
        allow($fs->write())->toReturnUsing(function (string $p, string $c) use (&$written) {
            $written[$p] = $c;
        });

        $provider = new ReplayProvider([
            new Response('', [new ToolCall('1', 'propose_edit', ['path' => 'src/App/Calc.php', 'content' => "<?php\nclass Calc {}"])]),
        ]);
        $cmd = new Generate(new Configuration('.', $fs), $fs, $provider);
        $tester = new CommandTester($cmd);

        $tester->execute(['instruction' => ['a', 'Calc', 'class']], ['interactive' => false]);

        $out = $tester->getDisplay();
        expect($out)->toContain('[NEW FILE]');
        expect($out)->toContain('src/App/Calc.php');
        expect($out)->toContain('Created');
        expect($written[getcwd() . '/src/App/Calc.php'] ?? '')->toContain('class Calc');
    });

    it('reports when nothing could be generated', function (Filesystem $fs) use ($withAi) {
        $withAi($fs);
        $cmd = new Generate(new Configuration('.', $fs), $fs, new ReplayProvider());
        $tester = new CommandTester($cmd);

        $tester->execute(['instruction' => ['x']], ['interactive' => false]);

        expect($tester->getDisplay())->toContain('did not produce a usable artifact');
    });

});
