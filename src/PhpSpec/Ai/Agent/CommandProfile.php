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

use PhpSpec\Ai\Prompt;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 * A command is data: its prompt file (`Ai/Prompts/commands/<name>.txt`) declares
 * everything the pipeline needs to know about it in YAML frontmatter (the tools
 * it may call, the answer channel, the grounding sections it wants, the model
 * params) followed by its prose. Tuning a command is a text edit, never code;
 * the pipeline itself carries no per-command branches.
 */
final readonly class CommandProfile
{
    /**
     * @param string $name the command name (its file is `commands/<name>.txt`)
     * @param string $body the prose after the frontmatter, the command's own voice
     * @param list<string> $tools names of the tools this command may call
     * @param 'tool_call'|'prose' $answer the channel the model must answer on
     * @param list<string> $grounding the grounding sections to build (e.g. recency, tree)
     * @param float|null $temperature sampling temperature, when the command pins one
     * @param int|null $maxTokens per-call output-token ceiling, when pinned
     * @param int|null $maxTurns tool-round ceiling for a conversational command, when pinned
     * @param string $origin where the winning prompt layer came from (Prompt::PROJECT or Prompt::SHIPPED)
     */
    public function __construct(
        public string $name,
        public string $body,
        public array $tools = [],
        public string $answer = 'prose',
        public array $grounding = [],
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public ?int $maxTurns = null,
        public string $origin = Prompt::SHIPPED,
    ) {}

    /**
     * Composes a command's profile from its prompt layers, nearest first: the
     * body is the first layer's prose, and each manifest key comes from the
     * first layer whose frontmatter declares it, so a prose-only project
     * override keeps the shipped machine contract.
     *
     * @throws RuntimeException when no layer exists or the manifest is invalid
     */
    public static function compose(string $name, Prompt ...$layers): self
    {
        if ($layers === []) {
            throw new RuntimeException(sprintf('Unknown AI command "%s": no "commands/%s.txt" prompt found.', $name, $name));
        }

        [$meta, $body] = self::split($layers[0]->text, $name);
        foreach (array_slice($layers, 1) as $layer) {
            [$layerMeta] = self::split($layer->text, $name);
            $meta += $layerMeta;
        }

        $answer = $meta['answer'] ?? 'prose';
        if (!in_array($answer, ['tool_call', 'prose'], true)) {
            throw new RuntimeException(sprintf('Invalid "answer" in "commands/%s.txt": expected "tool_call" or "prose", got "%s".', $name, (string) $answer));
        }

        return new self(
            $name,
            $body,
            self::names($meta['tools'] ?? []),
            $answer,
            self::names($meta['grounding'] ?? []),
            isset($meta['temperature']) ? (float) $meta['temperature'] : null,
            isset($meta['max_tokens']) ? (int) $meta['max_tokens'] : null,
            isset($meta['max_turns']) ? (int) $meta['max_turns'] : null,
            $layers[0]->origin,
        );
    }

    /**
     * Splits the file into its parsed frontmatter and its prose body. A file
     * with no frontmatter is all prose.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    private static function split(string $text, string $name): array
    {
        if (preg_match('~^---\R(.*?)\R---\R?(.*)$~s', $text, $matches) !== 1) {
            return [[], trim($text)];
        }

        try {
            $meta = Yaml::parse($matches[1]);
        } catch (ParseException $e) {
            throw new RuntimeException(sprintf('Invalid frontmatter in "commands/%s.txt": %s', $name, $e->getMessage()), 0, $e);
        }

        return [is_array($meta) ? $meta : [], trim($matches[2])];
    }

    /**
     * Normalises a frontmatter list into a clean list of strings.
     *
     * @param mixed $value the decoded frontmatter entry
     * @return list<string>
     */
    private static function names(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(strval(...), $value), static fn(string $name): bool => $name !== ''));
    }
}
