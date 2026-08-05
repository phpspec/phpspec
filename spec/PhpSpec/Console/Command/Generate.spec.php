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

    $proposing = fn(): ReplayProvider => new ReplayProvider([
        new Response('', [new ToolCall('1', 'propose_edit', ['path' => 'src/App/Calc.php', 'content' => "<?php\nclass Calc {}"])]),
    ]);

    // The offer book keeps itself on disk; a write to .phpspec is bookkeeping,
    // not a change to the project.
    $projectWrites = fn(array $written): array => array_filter(
        $written,
        fn(string $path): bool => !str_contains($path, '/.phpspec/'),
        ARRAY_FILTER_USE_KEY,
    );

    it('shows a NEW FILE diff and offers the change rather than writing it', function (Filesystem $fs) use ($withAi, $proposing, $projectWrites) {
        $withAi($fs);
        $written = [];
        allow($fs->write())->toReturnUsing(function (string $p, string $c) use (&$written) {
            $written[$p] = $c;
        });

        $cmd = new Generate(new Configuration('.', $fs), $fs, $proposing());
        $tester = new CommandTester($cmd);

        // Nobody to ask is not the same as a yes.
        $tester->execute(['instruction' => ['a', 'Calc', 'class']], ['interactive' => false]);

        $out = $tester->getDisplay();
        expect($out)->toContain('[NEW FILE]');
        expect($out)->toContain('src/App/Calc.php');
        expect($out)->toContain('phpspec accept o_');
        expect($projectWrites($written))->toBe([]);
    });

    it('gives an agent an id for each proposal, unapplied', function (Filesystem $fs) use ($withAi, $proposing, $projectWrites) {
        $withAi($fs);
        $written = [];
        allow($fs->write())->toReturnUsing(function (string $p, string $c) use (&$written) {
            $written[$p] = $c;
        });

        $cmd = new Generate(new Configuration('.', $fs), $fs, $proposing());
        $tester = new CommandTester($cmd);

        $tester->execute(['instruction' => ['a', 'Calc', 'class'], '--format' => 'agent'], ['interactive' => false]);

        $document = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
        expect($document['proposals'][0]['path'])->toBe('src/App/Calc.php');
        expect($document['proposals'][0]['applied'])->toBeFalse();
        expect($document['proposals'][0]['id'])->toStartWith('o_');
        expect($projectWrites($written))->toBe([]);
    });

    it('puts the proposal on the table, so accept can take exactly what was read', function (Filesystem $fs) use ($withAi, $proposing) {
        $withAi($fs);
        $stored = [];
        allow($fs->write())->toReturnUsing(function (string $p, string $c) use (&$stored) {
            $stored[$p] = $c;
        });

        $cmd = new Generate(new Configuration('.', $fs), $fs, $proposing());
        $tester = new CommandTester($cmd);

        $tester->execute(['instruction' => ['a', 'Calc', 'class'], '--format' => 'agent'], ['interactive' => false]);

        $book = json_decode($stored[getcwd() . '/.phpspec/offers.json'] ?? '{}', true);
        expect($book['offers'][0]['path'])->toBe('src/App/Calc.php');
        expect($book['offers'][0]['content'])->toContain('class Calc');
    });

    it('reports when nothing could be generated', function (Filesystem $fs) use ($withAi) {
        $withAi($fs);
        $cmd = new Generate(new Configuration('.', $fs), $fs, new ReplayProvider());
        $tester = new CommandTester($cmd);

        $tester->execute(['instruction' => ['x']], ['interactive' => false]);

        expect($tester->getDisplay())->toContain('no usable answer');
    });

});
