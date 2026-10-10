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

use Closure;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Throwable;

/**
 * @internal
 * How a refactoring shows where it is: a line saying what the model is doing,
 * completed with how long it took and how much it wrote, and the plan's
 * steps as a checklist, done, under way and to come.
 */
final readonly class Progress
{
    public function __construct(private Output $output) {}

    /**
     * Says what is under way, waits for the model's answer, then says how long
     * it took and how many tokens it wrote.
     *
     * @template T of RefactorPlan|Declined|RefactorStep
     * @param Closure(): T $answer
     * @return T
     */
    public function during(string $doing, Closure $answer): RefactorPlan|Declined|RefactorStep
    {
        $this->output->write(OutputFormatter::escape($doing) . '…');
        $start = hrtime(true);

        try {
            $answered = $answer();
        } catch (Throwable $e) {
            $this->output->writeln('');

            throw $e;
        }

        $this->output->writeln(sprintf(' <fg=gray>(%s)</>', $this->took((hrtime(true) - $start) / 1e9, $answered->tokens)));

        return $answered;
    }

    /**
     * The plan's steps, those before the current one done, the current one
     * under way and the rest to come; with no current step, all to come.
     */
    public function checklist(RefactorPlan $plan, ?int $current = null): void
    {
        foreach ($plan->steps as $index => $step) {
            $title = OutputFormatter::escape($step->title);

            $this->output->writeln(match (true) {
                $current !== null && $index < $current => "  <fg=green>✔</> $title",
                $index === $current => "  <options=bold>◼ $title</>",
                default => "  <fg=gray>◻ $title</>",
            });
        }
    }

    private function took(float $seconds, int $tokens): string
    {
        $whole = (int) round($seconds);
        $elapsed = $whole < 60 ? $whole . 's' : sprintf('%dm %ds', intdiv($whole, 60), $whole % 60);

        return match (true) {
            $tokens === 0 => $elapsed,
            $tokens < 1000 => sprintf('%s · ↓ %d tokens', $elapsed, $tokens),
            default => sprintf('%s · ↓ %.1fk tokens', $elapsed, $tokens / 1000),
        };
    }
}
