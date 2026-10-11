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

namespace PhpSpec\Console;

use PhpSpec\Console\Command\Pair\Chooser;
use PhpSpec\Console\Command\Pair\ScrollRegionOutput;
use PhpSpec\Console\Command\Run\Generation;
use Symfony\Component\Console\Output\OutputInterface as Output;

/**
 * @internal
 * Asks whoever is at the console before something is written: through the
 * pair chooser in a pair session, else on a [Y/n] line. An unanswered
 * question is not a yes: nothing is written, and the person reading the log
 * is told how to have it anyway.
 */
final readonly class Consent
{
    /**
     * @param string $remedy how to have it with nobody to answer, a sprintf
     *                       format taking the action ("Run with --accept-offers to %s.")
     * @param Generation $generation how a question is answered when nobody is asked
     * @param Chooser|null $chooser the pair chooser, when in a pair session
     * @param Prompt $prompt reads the person's answers
     */
    public function __construct(
        private string $remedy,
        private Generation $generation = Generation::Asks,
        private ?Chooser $chooser = null,
        private Prompt $prompt = new Prompt(),
    ) {}

    /**
     * Whether the person said yes.
     *
     * @param string $question the question to display, without a "[Y/n]" suffix
     * @param string $kind stable identifier grouping this question for chooser "always" memory
     * @param string $action verb phrase completing "always ..." in the chooser
     */
    public function confirm(Output $output, string $question, string $kind, string $action): bool
    {
        if ($this->chooser !== null) {
            return $this->chooser->choose($question, $kind, $action);
        }

        if ($this->generation === Generation::Accepts) {
            return true;
        }

        $asking = $this->generation === Generation::Asks;

        $output->writeln('');
        $output->writeln($asking ? "$question [Y/n] " : $question);

        $answer = $asking ? $this->answer($output) : null;

        if ($answer === null) {
            $this->nobody($output, $action);

            return false;
        }

        return $answer === '' || strtolower($answer) === 'y';
    }

    /**
     * What the person answered, or nothing when there was nobody there.
     */
    public function answer(Output $output): ?string
    {
        if ($output instanceof ScrollRegionOutput) {
            $output->prepareForInput();
        }

        $answer = $this->prompt->ask('  > ');

        if ($output instanceof ScrollRegionOutput && $answer !== null) {
            $output->returnToContent();
            $output->echoInput($answer ?: 'Y');
        }

        return $answer;
    }

    /**
     * Says that nothing was written because nobody was there to answer, and
     * how to have it anyway.
     */
    public function nobody(Output $output, string $action): void
    {
        $output->writeln(sprintf('  <fg=yellow>Nothing was written: there is nobody to answer. %s</>', sprintf($this->remedy, $action)));
    }
}
