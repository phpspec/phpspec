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

namespace PhpSpec\Ai\Agent;

use PhpSpec\Report\Formatter\Agent\Schema;

/**
 * @internal
 * Renders an Outcome as the machine-readable agent document, the one presenter
 * behind `--format=agent` for the AI commands: the resolved step, the
 * suggestion with the exact command that acts on it, the proposals as
 * path/action/applied receipts (never file content; the agent reads the file),
 * the prose, and the one number an agent checks: what is left to act on.
 */
final class OutcomePresenter
{
    /**
     * The document as an array.
     *
     * @param string $command the AI command that produced the outcome
     * @param list<bool> $applied whether each proposal (by index) reached disk
     * @param string|null $run the exact invocation that acts on the suggestion
     * @return array<string, mixed>
     */
    public function document(string $command, Outcome $outcome, array $applied = [], ?string $run = null): array
    {
        $document = [
            'command' => [
                'v' => Schema::V,
                'event' => 'command_result',
                'command' => $command,
            ],
        ];

        if ($outcome->step !== null) {
            $document['step'] = [
                'phase' => $outcome->step->phase->value,
                'path' => $outcome->step->path,
                'subject' => $outcome->step->subject,
                'because' => $outcome->step->because,
            ];
        }

        if ($outcome->data !== []) {
            $document['suggestion'] = $outcome->data;
            if ($run !== null) {
                $document['suggestion']['run'] = $run;
            }
        }

        $unapplied = 0;
        if ($outcome->proposals !== []) {
            $document['proposals'] = [];
            foreach ($outcome->proposals as $index => $proposal) {
                $wasApplied = $applied[$index] ?? false;
                $document['proposals'][] = [
                    'path' => $proposal->path,
                    'action' => $proposal->isNew ? 'create' : 'update',
                    'applied' => $wasApplied,
                ];
                $unapplied += $wasApplied ? 0 : 1;
            }
        }

        if ($outcome->prose !== '') {
            $document['prose'] = $outcome->prose;
        }

        $document['actionable'] = $unapplied + ($run !== null ? 1 : 0);

        return $document;
    }

    /**
     * The document as one raw JSON line.
     *
     * @param list<bool> $applied whether each proposal (by index) reached disk
     */
    public function render(string $command, Outcome $outcome, array $applied = [], ?string $run = null): string
    {
        return (json_encode($this->document($command, $outcome, $applied, $run), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}') . "\n";
    }
}
