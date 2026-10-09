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

    $withAi = fn(): Configuration => new Configuration(['ai' => ['provider' => 'openai', 'api_key' => 'test-key']]);

    it('errors without AI configuration', function (Filesystem $fs) {
        $cmd = new Generate(new Configuration());
        $tester = new CommandTester($cmd);

        $tester->execute(['instruction' => ['a', 'Calc']], ['interactive' => false]);

        expect($tester->getDisplay())->toContain('AI configuration required. Create phpspec.yaml with an "ai" section');
        expect($tester->getStatusCode())->toBe(1);
    });

    it('refuses in JSON under the agent format, naming the file to configure', function () {
        $tester = new CommandTester(new Generate(new Configuration()));

        $tester->execute(['instruction' => ['a', 'Calc'], '--format' => 'agent'], ['interactive' => false]);

        $document = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
        expect($document['action'])->toBe('generate');
        expect($document['error'])->toContain('Create phpspec.yaml with an "ai" section');
        expect($tester->getStatusCode())->toBe(1);
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
        $written = [];
        allow($fs->write())->toReturnUsing(function (string $p, string $c) use (&$written) {
            $written[$p] = $c;
        });

        $cmd = new Generate($withAi(), $fs, $proposing());
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
        $written = [];
        allow($fs->write())->toReturnUsing(function (string $p, string $c) use (&$written) {
            $written[$p] = $c;
        });

        $cmd = new Generate($withAi(), $fs, $proposing());
        $tester = new CommandTester($cmd);

        $tester->execute(['instruction' => ['a', 'Calc', 'class'], '--format' => 'agent'], ['interactive' => false]);

        $document = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
        expect($document['proposals'][0]['path'])->toBe('src/App/Calc.php');
        expect($document['proposals'][0]['applied'])->toBeFalse();
        expect($document['proposals'][0]['id'])->toStartWith('o_');
        expect($projectWrites($written))->toBe([]);
    });

    it('puts the proposal on the table, so accept can take exactly what was read', function (Filesystem $fs) use ($withAi, $proposing) {
        $stored = [];
        allow($fs->write())->toReturnUsing(function (string $p, string $c) use (&$stored) {
            $stored[$p] = $c;
        });

        $cmd = new Generate($withAi(), $fs, $proposing());
        $tester = new CommandTester($cmd);

        $tester->execute(['instruction' => ['a', 'Calc', 'class'], '--format' => 'agent'], ['interactive' => false]);

        $book = json_decode($stored[getcwd() . '/.phpspec/offers.json'] ?? '{}', true);
        expect($book['offers'][0]['target'])->toBe('src/App/Calc.php');
        expect($book['offers'][0]['data']['content'])->toContain('class Calc');
    });

    it('reports when nothing could be generated', function (Filesystem $fs) use ($withAi) {
        allow($fs->write());
        $cmd = new Generate($withAi(), $fs, new ReplayProvider());
        $tester = new CommandTester($cmd);

        $tester->execute(['instruction' => ['x']], ['interactive' => false]);

        expect($tester->getDisplay())->toContain('no usable answer');
    });

});
