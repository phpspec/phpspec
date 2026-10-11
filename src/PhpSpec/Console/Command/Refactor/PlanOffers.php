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

namespace PhpSpec\Console\Command\Refactor;

use InvalidArgumentException;
use PhpSpec\Ai\Agent\Writer;
use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\PromptLibrary;
use PhpSpec\Ai\ProviderFactory;
use PhpSpec\Ai\RefactorJournal;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Run\Generation;
use PhpSpec\Console\Consent;
use PhpSpec\Filesystem;
use PhpSpec\Offers\Offer;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface as Output;

/**
 * @internal
 * Takes a refactoring plan a coding agent was offered: the agent said yes
 * once, to the whole plan, so it is carried out with nobody asked, each step
 * written as the model writes it and the whole suite checked after it, as
 * `refactor --no-interaction` does.
 */
final readonly class PlanOffers
{
    /**
     * @param SpecSuite|null $suite the whole spec suite, the project's when not given
     * @param ProviderInterface|null $provider the model, the configured one when not given
     */
    public function __construct(
        private Configuration $config,
        private Filesystem $filesystem,
        private string $root,
        private ?SpecSuite $suite = null,
        private ?ProviderInterface $provider = null,
    ) {}

    /**
     * @return list<array{file: string, applied: bool, reason?: string}> the files the kept steps wrote, and why it stopped short when it did
     */
    public function take(Offer $offer, Output $output): array
    {
        $aiConfig = $this->config->getAiConfig();

        if ($aiConfig === null) {
            return [$this->notApplied((string) $this->config->aiConfigProblem())];
        }

        try {
            $target = (new TargetResolver($this->config, $this->filesystem, $this->root))->resolve($offer->target);
            $provider = $this->provider ?? ProviderFactory::create($aiConfig);
        } catch (RuntimeException|InvalidArgumentException $e) {
            return [$this->notApplied($e->getMessage())];
        }

        $suite = $this->suite ?? new SubprocessSuite($this->root, $this->config);
        $output->writeln('  <fg=gray>Checking the specs...</>');
        $baseline = $suite->check();

        if (!$baseline->green()) {
            return [$this->notApplied(trim('Specs must pass before refactoring. ' . implode(' ', $baseline->failures())))];
        }

        $plan = RefactorPlan::fromArray(is_array($offer->data['plan'] ?? null) ? $offer->data['plan'] : []);
        $model = new RefactorModel($provider, $this->config, $this->filesystem, $this->root, new PromptLibrary($this->filesystem));
        $journal = new RefactorJournal($this->filesystem);
        $model->adopt($target, $plan, $journal->rendered());

        $steps = new StepByStep($output, $model, $suite, new Consent('', Generation::Accepts), new Progress($output), new Writer($this->filesystem, $this->root), $journal, attended: false);
        $carried = $steps->carryOut($plan, $baseline, (string) ($offer->data['subject'] ?? $target->fqcn));
        $written = array_map(static fn(string $file): array => ['file' => $file, 'applied' => true], $carried->files);

        return $carried->exitCode === 0 && $carried->stopped === '' ? $written : [...$written, $this->notApplied($carried->stopped)];
    }

    /**
     * @return array{file: string, applied: false, reason: string}
     */
    private function notApplied(string $reason): array
    {
        return ['file' => '', 'applied' => false, 'reason' => $reason];
    }
}
