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

/**
 * @internal
 * Parses raw REPL input into a command name, an argument string, and the raw
 * tail. Commands work with or without a leading slash: a bare first word is
 * routed as a command only when the rest of the line unambiguously reads as
 * that command's arguments, so conversation is never swallowed by a command.
 */
final readonly class InputParser
{
    private const BARE_ONLY = ['next', 'help', 'swap', 'clear', 'quit', 'exit'];
    private const CLASS_FIRST = ['describe', 'exemplify', 'refactor'];
    private const RUN_KEYWORDS = ['features', 'feature', 'stories', 'story', 'specs', 'spec', 'all', 'everything'];

    /**
     * Splits raw input into a command, its argument, and the raw tail.
     *
     * The argument collects the non-option tokens; the tail is everything
     * after the first word verbatim, so option order (and option values)
     * survive for handlers that forward to a real command line.
     *
     * @param string $input the raw user input
     * @return array{command: string, argument: string, tail: string}
     */
    public function parse(string $input): array
    {
        $input = trim($input);

        if ($input === '') {
            return ['command' => '', 'argument' => '', 'tail' => ''];
        }

        $parts = preg_split('/\s+/', $input) ?: [$input];
        $command = strtolower($parts[0]);
        $tail = ltrim(substr($input, strlen($parts[0])));

        $args = [];
        $options = [];
        for ($i = 1, $count = count($parts); $i < $count; $i++) {
            if (str_starts_with($parts[$i], '-')) {
                $options[] = $parts[$i];
            } else {
                $args[] = $parts[$i];
            }
        }

        if (!str_starts_with($command, '/')) {
            $command = $this->route($command, $args, $options) ?? $command;
        }

        return ['command' => $command, 'argument' => implode(' ', $args), 'tail' => $tail];
    }

    /**
     * The slash command a bare first word maps to, or null when the line reads
     * as conversation. Guarded per command: bare-only words route alone
     * ("help", never "help me understand"), run routes when every token is
     * runnable, class-first commands need a class-like target, and generate
     * always routes because plain English is its argument.
     *
     * @param string $word the lowercased first word
     * @param list<string> $args the non-option tokens after the first word
     * @param list<string> $options the option tokens after the first word
     */
    private function route(string $word, array $args, array $options): ?string
    {
        if ($word === 'generate') {
            return '/generate';
        }

        if (in_array($word, self::BARE_ONLY, true)) {
            return $args === [] && $options === [] ? '/' . $word : null;
        }

        if ($word === 'run') {
            foreach ($args as $arg) {
                if (!$this->readsAsRunTarget($arg)) {
                    return null;
                }
            }

            return '/run';
        }

        if (in_array($word, self::CLASS_FIRST, true)) {
            return $args !== [] && $this->readsAsClass($args[0]) ? '/' . $word : null;
        }

        return null;
    }

    /**
     * Whether a run token reads as something runnable (a path, a spec or
     * feature file, a suite keyword) and never a word of prose.
     */
    private function readsAsRunTarget(string $token): bool
    {
        if (str_contains($token, '/') || str_ends_with($token, '.feature') || str_ends_with($token, '.php')) {
            return true;
        }

        return in_array(strtolower($token), self::RUN_KEYWORDS, true);
    }

    /**
     * Whether a token reads as a class-like target: identifier characters
     * (with namespace, path, or ::method separators) including at least one
     * capital letter, which prose words do not have.
     */
    private function readsAsClass(string $token): bool
    {
        return preg_match('#^[A-Za-z0-9_\\\\/:.]+$#', $token) === 1
            && preg_match('/[A-Z]/', $token) === 1;
    }
}
