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

use PhpSpec\Ai\Response;
use PhpSpec\Ai\ToolCall;
use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;

/**
 * @internal
 * Captures every agent exchange to `.phpspec/ai/last-request.json` in the eval
 * recording schema (a superset), so the debug log IS a recording: promoting a
 * misbehaviour to a permanent regression fixture under
 * `spec/PhpSpec/Ai/Eval/recordings/` is one copy plus a grader. Credentials are
 * never written.
 */
final class Recorder
{
    private const CAPTURE_FILE = '.phpspec/ai/last-request.json';

    private readonly Filesystem $filesystem;

    /**
     * @param Filesystem|null $filesystem filesystem abstraction for testability
     * @param string|null $baseDir the project dir to capture under, defaulting to the cwd
     */
    public function __construct(?Filesystem $filesystem = null, private readonly ?string $baseDir = null)
    {
        $this->filesystem = $filesystem ?? new RealFilesystem();
    }

    /**
     * Writes the capture document for one `do()` call. A deterministic run has
     * a null request and response; the step and proposals still tell the story.
     *
     * @param array{provider?: string, model?: string, api_key?: string, effort?: string} $aiConfig
     * @param list<Proposal> $proposals
     */
    public function capture(string $command, string $instruction, ?Step $step, ?Request $request, array $aiConfig, ?Response $response, array $proposals = []): void
    {
        $file = ($this->baseDir ?? (getcwd() ?: '.')) . '/' . self::CAPTURE_FILE;
        $dir = dirname($file);
        if (!$this->filesystem->exists($dir)) {
            $this->filesystem->mkdir($dir);
        }

        $this->filesystem->write($file, (string) json_encode(
            $this->document($command, $instruction, $step, $request, $aiConfig, $response, $proposals),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        ));
    }

    /**
     * The capture document. Keys mirror the eval recordings ("case",
     * "instruction", "aiConfig", "response") plus the pipeline's own view
     * (step, request, proposals). The api_key is deliberately dropped.
     *
     * @param array{provider?: string, model?: string, api_key?: string, effort?: string} $aiConfig
     * @param list<Proposal> $proposals
     * @return array<string, mixed>
     */
    private function document(string $command, string $instruction, ?Step $step, ?Request $request, array $aiConfig, ?Response $response, array $proposals): array
    {
        return [
            'case' => '',
            'command' => $command,
            'instruction' => $instruction,
            'aiConfig' => ['provider' => $aiConfig['provider'] ?? '', 'model' => $aiConfig['model'] ?? null, 'effort' => $aiConfig['effort'] ?? null],
            'step' => $step === null ? null : [
                'phase' => $step->phase->value,
                'path' => $step->path,
                'subject' => $step->subject,
                'because' => $step->because,
            ],
            'request' => $request === null ? null : [
                'system' => $request->system,
                'context' => $request->context,
                'composed_from' => $request->composedFrom,
            ],
            'response' => $response === null ? null : [
                'text' => $response->text,
                'tool_calls' => array_map(
                    static fn(ToolCall $call): array => ['id' => $call->id, 'name' => $call->name, 'arguments' => $call->arguments],
                    $response->toolCalls,
                ),
            ],
            'proposals' => array_map(
                static fn(Proposal $proposal): array => [
                    'path' => $proposal->path,
                    'isNew' => $proposal->isNew,
                    'origin' => $proposal->origin,
                    'new' => $proposal->new,
                ],
                $proposals,
            ),
        ];
    }
}
