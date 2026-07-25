<?php

use PhpSpec\Configuration;
use PhpSpec\Console\Command\Next;
use PhpSpec\Console\Command\Pair\SpecRunner;
use PhpSpec\Console\Command\Run\RunOutcome;
use PhpSpec\Console\Command\Run\SuiteSummary;
use PhpSpec\Filesystem;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

// A fake runner that records its argument and returns a scripted outcome.
class NextFeaturesPathRunner implements SpecRunner
{
    /** @var list<string> */
    public array $arguments = [];

    public ?RunOutcome $outcome = null;

    public function run(string $argument, OutputInterface $output): ?RunOutcome
    {
        $this->arguments[] = $argument;

        return $this->outcome;
    }
}

// E11 (eval) — `next` must discover features under the CONFIGURED features_path,
// not a hardcoded features/. With `features_path: acceptance`, a project holding
// acceptance/ features has to be seen as having features (run --all, grounded on
// their state), not treated as spec-only. RED without the config-aware gate.
describe('E11 next: discovers features under the configured features_path', function () {

    it('detects a custom features_path and grounds the suggestion in the feature state', function (Filesystem $fs) {
        $yaml = './phpspec.yaml';
        $featuresDir = getcwd() . '/acceptance';
        allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yaml || $p === $featuresDir);
        allow($fs->isDir())->toReturnUsing(fn(string $p): bool => $p === $featuresDir);
        allow($fs->isFile())->toReturn(false);
        allow($fs->scandir())->toReturn([]);
        allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $yaml
            ? "features_path: acceptance\nai:\n  provider: google\n  api_key: test-key\n"
            : '');

        $runner = new NextFeaturesPathRunner();
        $runner->outcome = new RunOutcome(null, new SuiteSummary(
            'green',
            ['examples' => 0, 'passes' => 0, 'failures' => 0, 'errors' => 0, 'pending' => 0],
            [],
            [],
            ['features' => 1, 'scenarios' => 1, 'steps' => 3, 'stepFailures' => 0, 'undefined' => 3],
            [['path' => 'acceptance/adding.feature', 'status' => 'todo', 'undefined' => 3]],
        ));

        $captured = '';
        $suggestFn = function (array $aiConfig, string $context) use (&$captured): array {
            $captured = $context;

            return ['type' => 'info', 'target' => '', 'reason' => 'ok'];
        };
        $cmd = new Next(new Configuration('.', $fs), $fs, $suggestFn, $runner);

        (new CommandTester($cmd))->execute([]);

        expect($runner->arguments)->toContain('--all'); // ran the features (gate saw the custom dir)
        expect($captured)->toContain('FEATURES');        // grounded the suggestion in their state
    });

});
