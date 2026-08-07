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

use PhpSpec\Ai\Agent\Proposal;
use PhpSpec\Ai\Agent\Writer;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Run\CodeGenerator;
use PhpSpec\Console\Command\Run\Generation;
use PhpSpec\Console\Command\Run\GenerationCandidates;
use PhpSpec\Filesystem;
use PhpSpec\Offers\Offer;
use PhpSpec\Offers\OfferBook;
use PhpSpec\RealFilesystem;
use PhpSpec\Report\Formatter\Agent\Schema;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputOption as Option;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface as Output;

/**
 * @internal
 * Takes an offer PhpSpec made earlier, by its id.
 *
 * Deciding requires seeing, and seeing happened in an earlier command, so this
 * is where a decision is carried out: it applies exactly what was shown, or
 * refuses, and never something else. Nothing is applied unless every named
 * offer can be, so a batch cannot leave the project half changed.
 */
final class Accept extends Command
{
    private readonly Filesystem $filesystem;

    private readonly OfferBook $book;

    private readonly Configuration $config;

    public function __construct(
        ?Filesystem $filesystem = null,
        ?OfferBook $book = null,
        private readonly ?string $baseDir = null,
        ?Configuration $config = null,
    ) {
        $this->filesystem = $filesystem ?? new RealFilesystem();
        $this->book = $book ?? new OfferBook($this->filesystem, $baseDir);
        $this->config = $config ?? new Configuration($baseDir ?? '.', $this->filesystem);

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('accept')
            ->setDescription('Apply an offer PhpSpec made, by its id')
            ->setDefinition([
                new Argument('offer', Argument::REQUIRED | Argument::IS_ARRAY, 'The id of an offer to apply'),
            ])
            ->addOption('format', 'f', Option::VALUE_REQUIRED, 'Output format: pretty, or agent (machine-readable JSON receipt for coding agents)', 'pretty');
    }

    protected function execute(Input $input, Output $output): int
    {
        $forAgent = $input->getOption('format') === 'agent';
        /** @var list<string> $ids */
        $ids = (array) $input->getArgument('offer');

        $taking = [];

        foreach ($ids as $id) {
            $offer = $this->book->find($id);

            if ($offer === null) {
                return $this->refuse(
                    sprintf('No offer "%s" is on the table. Offers are made by the command that proposes the change; run it again to get a fresh one.', $id),
                    $forAgent,
                    $output,
                );
            }

            if ($offer->kind === Offer::WRITE && $offer->staleAgainst($this->contentOf($offer->target))) {
                return $this->refuse(
                    sprintf('Offer "%s" no longer fits %s, which changed since the offer was made.', $id, $offer->target),
                    $forAgent,
                    $output,
                );
            }

            $taking[] = $offer;
        }

        return $this->take($taking, $forAgent, $output);
    }

    /**
     * Applies the offers, all of which were checked first.
     *
     * @param list<Offer> $offers
     */
    private function take(array $offers, bool $forAgent, Output $output): int
    {
        $receipts = [];
        $notes = $forAgent ? new NullOutput() : $output;

        foreach ($offers as $offer) {
            match ($offer->kind) {
                Offer::GENERATE => $this->generate($offer, $notes),
                default => $this->write($offer, $notes),
            };

            $receipts[] = [
                'id' => $offer->id,
                'path' => $offer->target,
                'action' => $offer->action,
                'applied' => true,
            ];
        }

        if ($forAgent) {
            $this->emit(['v' => Schema::V, 'action' => 'accept', 'accepted' => $receipts], $output);
        }

        return 0;
    }

    /**
     * Applies a change that was proposed in full: the content travelled with
     * the offer, so what lands is what was read.
     */
    private function write(Offer $offer, Output $output): void
    {
        (new Writer($this->filesystem, $this->baseDir))
            ->apply(new Proposal($offer->target, $offer->was(), $offer->content(), $offer->action === 'create', 'accept'));

        $output->writeln(sprintf(
            '  <fg=green>%s %s</>',
            $offer->action === 'create' ? 'Created' : 'Updated',
            $offer->target,
        ));
    }

    /**
     * Generates the one thing this offer named, through the same generator the
     * interactive runner uses. The generators never overwrite, so an offer
     * whose subject now exists quietly does nothing rather than clobbering it.
     */
    private function generate(Offer $offer, Output $output): void
    {
        $candidates = is_array($offer->data['candidates'] ?? null) ? $offer->data['candidates'] : [];

        $generator = new CodeGenerator(
            ltrim($this->config->getSrcPath(), './'),
            ltrim($this->config->getSpecPath(), './'),
            // Asked for by name on the command line: the person has already
            // said yes, and there is nothing left to put to them.
            Generation::Accepts,
            $this->config->getSpecSuffix(),
            $this->config->getPsr4Prefix(),
        );

        $generator->apply($output, GenerationCandidates::fromArray($candidates), $offer->action === 'fake_method');
    }

    /**
     * Reports why nothing was applied, on whichever channel the caller reads.
     */
    private function refuse(string $problem, bool $forAgent, Output $output): int
    {
        if ($forAgent) {
            $this->emit(['v' => Schema::V, 'action' => 'accept', 'accepted' => [], 'error' => $problem], $output);

            return 1;
        }

        $output->writeln(sprintf('<fg=red>%s</>', $problem));

        return 1;
    }

    /**
     * The file's content now, or null when it is not there.
     */
    private function contentOf(string $path): ?string
    {
        $file = ($this->baseDir ?? (getcwd() ?: '.')) . '/' . $path;

        return $this->filesystem->exists($file) ? $this->filesystem->read($file) : null;
    }

    /**
     * @param array<string, mixed> $document
     */
    private function emit(array $document, Output $output): void
    {
        $json = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';

        $output->write($json . "\n", false, Output::OUTPUT_RAW);
    }
}
