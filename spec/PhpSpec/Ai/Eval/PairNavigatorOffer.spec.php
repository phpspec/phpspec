<?php

use PhpSpec\Configuration;
use PhpSpec\Console\Command\Pair\AiAssistant;
use PhpSpec\Console\Command\Pair\Chooser;
use PhpSpec\Console\Command\Pair\PairOutput;
use PhpSpec\Console\Command\Run\RunOutcome;
use PhpSpec\Console\Command\Pair\SpecRunner;
use PhpSpec\Filesystem;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

require_once __DIR__ . '/../ReplayProvider.php';

// E12 (eval) — a recorded live pair session (navigator reads the class twice,
// offers one small change, winds down in prose) replayed through the whole
// pair stack: the offer must render as a diff and land only on a yes, and a
// decline must steer the model instead of writing.
describe('E12 pair: navigator offer, replayed from a live session', function () {

    beforeEach(function (Filesystem $fs) {
        $source = "<?php\nnamespace App;\nclass TodoList {\n    public function completedTasks(): array\n    {\n        return array_keys(array_filter(\$this->tasks, fn(bool \$isComplete) => \$isComplete));\n    }\n}\n";
        allow($fs->exists())->toReturnUsing(fn(string $path): bool => str_ends_with($path, 'src/App/TodoList.php'));
        allow($fs->read())->toReturnUsing(fn(string $path): string => str_ends_with($path, 'src/App/TodoList.php') ? $source : '');
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
        allow($fs->scandir())->toReturn([]);
        allow($fs->mkdir())->toReturn(null);
        $this->written = [];
        $written = &$this->written;
        allow($fs->write())->toReturnUsing(function (string $path, string $content) use (&$written) {
            $written[$path] = $content;
        });
        $this->artifacts = fn() => array_values(array_filter(array_keys($this->written), fn(string $path) => !str_contains($path, '.phpspec/ai/')));

        $this->recording = json_decode((string) file_get_contents(__DIR__ . '/recordings/pair-navigator-offer.json'), true);
        $this->buffer = new BufferedOutput();
        $this->pairOutput = new PairOutput($this->buffer);
        $this->specRunner = new class implements SpecRunner {
            public function run(string $argument, OutputInterface $output): ?RunOutcome
            {
                return null;
            }
        };
        $this->answers = [];
        $this->chooser = new Chooser($this->pairOutput, true, fn() => array_shift($this->answers) ?? '');
    });

    it('renders the recorded offer as a diff and applies it on yes', function (Filesystem $fs) {
        $replay = ReplayProvider::fromConversation($this->recording);
        $assistant = new AiAssistant($replay, new Configuration('.', $fs), $this->pairOutput, $fs, true, null, $this->chooser, null, $this->specRunner);

        $this->answers = ['1'];
        $assistant->handle($this->recording['turns'][0]['instruction']);

        $text = $this->buffer->fetch();
        expect($text)->toContain('[MODIFIED]');                       // the diff was shown before the decision
        expect(($this->artifacts)())->toHaveLength(1);                // the offered change landed once
        expect($this->written[($this->artifacts)()[0]])->toContain('completedTasks');
    });

    it('keeps a declined offer unwritten and hands the model the decline steer', function (Filesystem $fs) {
        $replay = ReplayProvider::fromConversation($this->recording);
        $assistant = new AiAssistant($replay, new Configuration('.', $fs), $this->pairOutput, $fs, true, null, $this->chooser, null, $this->specRunner);

        $this->answers = ['3, offer the smallest step first'];
        $assistant->handle($this->recording['turns'][0]['instruction']);

        expect(($this->artifacts)())->toBe([]);                       // nothing landed
        $lastRequest = (string) json_encode(end($replay->requests)['messages']);
        expect($lastRequest)->toContain('declined this offer');       // the steer reached the model
        expect($lastRequest)->toContain('offer the smallest step first');
    });

});
