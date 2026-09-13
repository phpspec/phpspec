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
 * A command's manifest and prose, read from its prompt file layers.
 */
final readonly class CommandProfile
{
    /**
     * @param string $name
     * @param string $body
     * @param list<string> $tools
     * @param 'tool_call'|'prose' $answer
     * @param list<string> $grounding
     * @param float|null $temperature
     * @param int|null $maxTokens
     * @param int|null $maxTurns
     * @param string $origin Prompt::PROJECT|Prompt::SHIPPED
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
     * Composes the profile from prompt layers, nearest first.
     *
     * @throws RuntimeException when no layer exists or the manifest is invalid
     */
    public static function compose(string $name, Prompt ...$layers): self
    {
        if ($layers === []) {
            throw new RuntimeException(sprintf('Unknown AI command "%s": no "commands/%s.txt" prompt found.', $name, $name));
        }

        [$meta, $body] = self::frontmatterAndBody($layers[0]->text, $name);
        foreach (array_slice($layers, 1) as $layer) {
            [$layerMeta] = self::frontmatterAndBody($layer->text, $name);
            $meta += $layerMeta;
        }

        $answer = $meta['answer'] ?? 'prose';
        if (!in_array($answer, ['tool_call', 'prose'], true)) {
            throw new RuntimeException(sprintf('Invalid "answer" in "commands/%s.txt": expected "tool_call" or "prose", got "%s".', $name, (string) $answer));
        }

        return new self(
            $name,
            $body,
            self::nonEmptyStrings($meta['tools'] ?? []),
            $answer,
            self::nonEmptyStrings($meta['grounding'] ?? []),
            isset($meta['temperature']) ? (float) $meta['temperature'] : null,
            isset($meta['max_tokens']) ? (int) $meta['max_tokens'] : null,
            isset($meta['max_turns']) ? (int) $meta['max_turns'] : null,
            $layers[0]->origin,
        );
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    private static function frontmatterAndBody(string $text, string $name): array
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
     * @return list<string>
     */
    private static function nonEmptyStrings(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(strval(...), $value), static fn(string $name): bool => $name !== ''));
    }
}
