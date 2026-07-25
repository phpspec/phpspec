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

use PhpSpec\Ai\PromptLibrary;
use PhpSpec\Filesystem;
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
     */
    public function __construct(
        public string $name,
        public string $body,
        public array $tools = [],
        public string $answer = 'prose',
        public array $grounding = [],
        public ?float $temperature = null,
        public ?int $maxTokens = null,
    ) {}

    /**
     * Loads a command's profile from its prompt file, parsing the optional YAML
     * frontmatter into the manifest fields.
     *
     * @throws RuntimeException when the file is missing or the manifest is invalid
     */
    public static function load(string $name, ?Filesystem $filesystem = null): self
    {
        $text = (new PromptLibrary($filesystem))->read('commands/' . $name);
        if (trim($text) === '') {
            throw new RuntimeException(sprintf('Unknown AI command "%s": no "commands/%s.txt" prompt found.', $name, $name));
        }

        [$meta, $body] = self::split($text, $name);

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
