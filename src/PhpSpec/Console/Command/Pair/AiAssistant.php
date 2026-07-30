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

namespace PhpSpec\Console\Command\Pair;

use PhpSpec\Ai\Agent\Agent;
use PhpSpec\Ai\Agent\Grounding;
use PhpSpec\Ai\Agent\Transcript;
use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\PromptLibrary;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Run\SuiteSummary;
use PhpSpec\Extensions\ExtensionLoader;
use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;
use Throwable;

/**
 * @internal
 * The pair session's conversational shell: one user turn is one
 * `Agent::chat()` on the session's persistent Transcript, with the
 * PairToolExecutor executing tools live inside the loop. This class holds no
 * provider call, no message array, and no prompt assembly; it names the role's
 * command, seeds the suite state, and renders the outcome.
 */
final class AiAssistant
{
    private readonly RoleState $roleState;

    /** The session's tool half: surfaces, live execution, and turn settling. */
    private readonly PairToolExecutor $executor;

    /** The one pipeline verb, seeded with this session's transcript and executor. */
    private readonly Agent $agent;

    /**
     * @param ProviderInterface $provider the AI provider for LLM interactions
     * @param Configuration $config the project configuration
     * @param PairOutput $output the pair-mode output helper
     * @param Filesystem|null $filesystem filesystem abstraction for testability
     * @param bool $interactive whether to prompt for user confirmation on write actions
     * @param ExtensionLoader|null $extensionLoader optional extension loader for custom tools
     * @param Chooser|null $chooser the interactive chooser; shared with the dispatcher
     *                              so "always" answers span the whole pair session
     * @param RoleState|null $roleState the current pairing role; shared with the
     *                                  dispatcher so a /swap is seen by both
     * @param SpecRunner|null $specRunner runs specs for run_specs and auto-verify;
     *                                    defaults to a fresh subprocess runner
     */
    public function __construct(
        ProviderInterface $provider,
        Configuration $config,
        private readonly PairOutput $output,
        ?Filesystem $filesystem = null,
        bool $interactive = true,
        ?ExtensionLoader $extensionLoader = null,
        ?Chooser $chooser = null,
        ?RoleState $roleState = null,
        ?SpecRunner $specRunner = null,
    ) {
        $filesystem ??= new RealFilesystem();
        $prompts = new PromptLibrary($filesystem);
        $this->roleState = $roleState ?? new RoleState();
        $this->executor = new PairToolExecutor(
            $config,
            $filesystem,
            $output,
            $chooser ?? new Chooser($output, $interactive),
            $this->roleState,
            $specRunner ?? new SubprocessRunner(),
            $prompts,
            $extensionLoader,
        );
        $this->agent = new Agent(
            $config,
            $filesystem,
            $provider,
            prompts: $prompts,
            transcript: new Transcript(),
            executor: $this->executor,
        );
    }

    /**
     * Sends natural-language input through the agent pipeline and displays the
     * response. The persistent transcript preserves conversation history
     * between calls; a supplied suite summary seeds the turn's grounding, so
     * the model reacts to the same red/green reality the human sees.
     */
    public function handle(string $input, ?SuiteSummary $situation = null): void
    {
        $this->output->getOutput()->writeln('');
        $this->output->getOutput()->writeln('  <fg=gray>Thinking...</>');

        try {
            PairLogger::log('INPUT', $input);

            $outcome = $this->agent->chat($this->roleState->current()->commandName(), $input, new Grounding(suite: $situation));

            $text = self::withoutMachineSuggestion($outcome->prose);
            PairLogger::log('RESPONSE', $text);

            if ($text !== '') {
                $this->output->getOutput()->writeln('');
                foreach (explode("\n", $text) as $line) {
                    $this->output->getOutput()->writeln("  $line");
                }
                $this->output->getOutput()->writeln('');
            }
        } catch (Throwable $e) {
            $this->output->error("AI error: {$e->getMessage()}");
        }
    }

    /**
     * The structured suggestion the model registered on its last turn (via the
     * suggest_next tool), or null when it registered none. The dispatcher turns
     * it into a ghost-prefilled /generate.
     *
     * @return array<string, string>|null
     */
    public function lastSuggestion(): ?array
    {
        return $this->executor->lastSuggestion();
    }

    /**
     * Strips a stray machine-readable {type,target,reason} suggestion block from
     * conversational output. That JSON is the standalone `next` command's contract
     * for a script to parse, never something the human should read in pair mode,
     * so an output rail removes it no matter why the model emitted it.
     */
    private static function withoutMachineSuggestion(string $text): string
    {
        $stripped = preg_replace('~\{(?=[^{}]*"type")(?=[^{}]*"target")(?=[^{}]*"reason")[^{}]*}~', '', $text);

        return trim((string) $stripped);
    }
}
