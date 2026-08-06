<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 * (c) Ciaran McNulty <ciaran@ciaranmcnulty.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Console\Command;

use PhpSpec\Configuration;
use PhpSpec\Filesystem;
use PhpSpec\Guard\Activation;
use PhpSpec\Guard\Baseline;
use PhpSpec\Guard\ShellGit;
use PhpSpec\RealFilesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Output\OutputInterface as Output;

/**
 * @internal
 * Turns guard on, and marks where this session starts.
 *
 * Guard's judgement is about what changed since a point in time, so there has
 * to be a point in time: this records one. In a repository that is the current
 * commit, because committing is a deliberate act of acceptance; without one it
 * is a snapshot of the guarded files as they stand.
 */
final class Guard extends Command
{
    private readonly Filesystem $filesystem;

    private readonly Configuration $config;

    public function __construct(
        ?Filesystem $filesystem = null,
        private readonly ?string $baseDir = null,
        ?Configuration $config = null,
    ) {
        $this->filesystem = $filesystem ?? new RealFilesystem();
        $this->config = $config ?? new Configuration($baseDir ?? '.', $this->filesystem);

        parent::__construct('guard');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Turns the TDD guard on and records where this session starts')
            ->setHelp('Records a baseline, then refuses a run whose changed logic no example covers.');
    }

    protected function execute(Input $input, Output $output): int
    {
        $problem = $this->config->guardConfigProblem();
        if ($problem !== null) {
            $output->writeln('<fg=red>' . $problem . '</>');

            return self::FAILURE;
        }

        $base = $this->baseDir ?? '.';
        $written = (new Activation($this->filesystem, $base))->turnOn();

        if ($written === null) {
            $output->writeln('<fg=yellow>Add "guard: {status: active}" to your phpspec config: this command edits YAML only.</>');

            return self::FAILURE;
        }

        $guard = $this->config->getGuardConfig();
        $paths = $guard['paths'] ?? ['src'];

        $recorded = (new Baseline($this->filesystem, new ShellGit($base), $base))->record($paths);

        $output->writeln(sprintf('Config %s updated. Guard is on.', basename($written)));
        $output->writeln($recorded['kind'] === 'commit'
            ? sprintf('Baseline recorded at %s.', substr($recorded['commit'] ?? '', 0, 12))
            : sprintf('Baseline recorded from %d file(s): this project is not a git repository.', count($recorded['files'] ?? [])));

        return self::SUCCESS;
    }
}
