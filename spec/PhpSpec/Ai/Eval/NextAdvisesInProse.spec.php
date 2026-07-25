<?php

use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\Response;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Pair\AiAssistant;
use PhpSpec\Console\Command\Pair\Chooser;
use PhpSpec\Console\Command\Pair\PairOutput;
use PhpSpec\Console\Command\Pair\SpecRunner;
use PhpSpec\Console\Command\Run\RunOutcome;
use PhpSpec\Filesystem;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

// Replays a recorded /next reply through AiAssistant and returns what the human
// would see, so the graders assert on the real rendered output.
function evalNextOutput(Filesystem $fs, string $case): string
{
    allow($fs->exists())->toReturn(false);
    allow($fs->read())->toReturn('');
    allow($fs->isDir())->toReturn(false);
    allow($fs->isFile())->toReturn(false);
    allow($fs->scandir())->toReturn([]);

    $rec = json_decode((string) file_get_contents(__DIR__ . '/recordings/' . $case . '.json'), true);

    $buffer = new BufferedOutput();
    $pairOutput = new PairOutput($buffer);

    $provider = new class implements ProviderInterface {
        public string $reply = '';

        public function chat(array $messages, array $options = []): Response
        {
            return new Response($this->reply);
        }
    };
    $provider->reply = $rec['response']['text'];

    $specRunner = new class implements SpecRunner {
        public function run(string $argument, OutputInterface $output): ?RunOutcome
        {
            return null;
        }
    };

    $assistant = new AiAssistant($provider, new Configuration('.', $fs), $pairOutput, 'test-model', $fs, false, null, new Chooser($pairOutput, false), null, $specRunner);
    $assistant->handle($rec['instruction']);

    return $buffer->fetch();
}

// E8 & E9 (evals) — pair /next advises in prose. The machine-readable
// {type,target,reason} JSON (the standalone `next` command's contract) must never
// reach the human. E8 replays a reply that leaked it; E9 a clean one.
describe('E8/E9 pair next advises in prose, never leaking the JSON contract', function () {

    it('strips a leaked JSON suggestion while keeping the advice (E8)', function (Filesystem $fs) {
        $output = evalNextOutput($fs, 'next-json-leak');

        expect($output)->not()->toContain('{"type"');
        expect($output)->not()->toContain('"reason"');
        expect($output)->toContain('scenario');
    });

    it('renders a clean advisory reply unchanged (E9)', function (Filesystem $fs) {
        $output = evalNextOutput($fs, 'next-advises-in-prose');

        expect($output)->not()->toContain('{"type"');
        expect($output)->toContain('refactor');
    });

});
