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

namespace PhpSpec\StoryBDD;

use InvalidArgumentException;
use Stringable;

/**
 * @internal
 * Which scenarios a run selects by their tags, written the Cucumber way:
 * "@smoke and not @wip", "(@a or @b) and not @c". "not" binds tightest, then
 * "and", then "or". An expression that cannot be read is refused when it is
 * made, naming what was found where.
 */
final class TagExpression implements Stringable
{
    private readonly string $expression;

    /** @var list<string> */
    private array $tokens = [];

    private int $at = 0;

    /** @var array<mixed> the expression as a tree: [tag, name], [not, node], [and, left, right], [or, left, right] */
    private readonly array $tree;

    public function __construct(string $expression)
    {
        $this->expression = trim($expression);

        if ($this->expression === '') {
            throw new InvalidArgumentException('Tag expression "" is empty.');
        }

        preg_match_all('/\(|\)|[^\s()]+/', $this->expression, $matches);
        $this->tokens = $matches[0];
        $this->tree = $this->orExpression();

        if ($this->at < count($this->tokens)) {
            throw $this->unexpected('"and", "or" or the end');
        }
    }

    /**
     * Whether a scenario carrying these tags is selected.
     *
     * @param array<string> $tags the tags, with or without their @
     */
    public function matches(array $tags): bool
    {
        $bare = array_values(array_map(static fn(string $tag): string => ltrim($tag, '@'), $tags));

        return $this->holds($this->tree, $bare);
    }

    public function __toString(): string
    {
        return $this->expression;
    }

    /**
     * @param array<mixed> $node
     * @param list<string> $tags
     */
    private function holds(array $node, array $tags): bool
    {
        return match ($node[0]) {
            'tag' => in_array($node[1], $tags, true),
            'not' => !$this->holds($node[1], $tags),
            'and' => $this->holds($node[1], $tags) && $this->holds($node[2], $tags),
            default => $this->holds($node[1], $tags) || $this->holds($node[2], $tags),
        };
    }

    /**
     * @return array<mixed>
     */
    private function orExpression(): array
    {
        $left = $this->andExpression();

        while ($this->peek() === 'or') {
            $this->at++;
            $left = ['or', $left, $this->andExpression()];
        }

        return $left;
    }

    /**
     * @return array<mixed>
     */
    private function andExpression(): array
    {
        $left = $this->notExpression();

        while ($this->peek() === 'and') {
            $this->at++;
            $left = ['and', $left, $this->notExpression()];
        }

        return $left;
    }

    /**
     * @return array<mixed>
     */
    private function notExpression(): array
    {
        if ($this->peek() === 'not') {
            $this->at++;

            return ['not', $this->notExpression()];
        }

        return $this->primary();
    }

    /**
     * @return array<mixed>
     */
    private function primary(): array
    {
        $token = $this->peek();

        if ($token === null) {
            throw new InvalidArgumentException(sprintf('Tag expression "%s" ends where a tag was expected.', $this->expression));
        }

        if ($token === '(') {
            $this->at++;
            $inner = $this->orExpression();

            if ($this->peek() !== ')') {
                throw $this->peek() === null
                    ? new InvalidArgumentException(sprintf('Tag expression "%s" ends where ")" was expected.', $this->expression))
                    : $this->unexpected('")"');
            }
            $this->at++;

            return $inner;
        }

        if (in_array($token, ['and', 'or', 'not', ')'], true)) {
            throw $this->unexpected('a tag');
        }

        $this->at++;

        return ['tag', ltrim($token, '@')];
    }

    private function peek(): ?string
    {
        return $this->tokens[$this->at] ?? null;
    }

    private function unexpected(string $expected): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf(
            'Tag expression "%s" has "%s" where %s was expected.',
            $this->expression,
            $this->tokens[$this->at],
            $expected,
        ));
    }
}
