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
 * The text being typed and where the cursor is in it, counted in characters,
 * and what each editing key makes of them.
 */
final readonly class TypedLine
{
    public int $cursor;

    public function __construct(
        public string $text = '',
        ?int $cursor = null,
    ) {
        $this->cursor = $cursor ?? mb_strlen($text);
    }

    /**
     * The line after an editing key; a key that does not edit leaves it as it is.
     */
    public function after(Key $key): self
    {
        return match ($key->name) {
            KeyName::Text => $this->inserted($key->text),
            KeyName::NewLine => $this->inserted("\n"),
            KeyName::Backspace => $this->cursor === 0 ? $this : new self($this->before($this->cursor - 1) . $this->from($this->cursor), $this->cursor - 1),
            KeyName::Delete => $this->cursor === $this->length() ? $this : new self($this->before($this->cursor) . $this->from($this->cursor + 1), $this->cursor),
            KeyName::Left => $this->at(max(0, $this->cursor - 1)),
            KeyName::Right => $this->at(min($this->length(), $this->cursor + 1)),
            KeyName::Home => $this->at($this->lineStart()),
            KeyName::End => $this->at($this->lineEnd()),
            KeyName::WordLeft => $this->at($this->wordStart()),
            KeyName::WordRight => $this->at($this->wordEnd()),
            default => $this,
        };
    }

    public function at(int $cursor): self
    {
        return new self($this->text, $cursor);
    }

    private function inserted(string $text): self
    {
        return new self($this->before($this->cursor) . $text . $this->from($this->cursor), $this->cursor + mb_strlen($text));
    }

    private function lineStart(): int
    {
        $break = mb_strrpos($this->before($this->cursor), "\n");

        return $break === false ? 0 : $break + 1;
    }

    private function lineEnd(): int
    {
        $break = mb_strpos($this->from($this->cursor), "\n");

        return $break === false ? $this->length() : $this->cursor + $break;
    }

    private function wordStart(): int
    {
        $before = $this->before($this->cursor);

        return mb_strlen((string) preg_replace('/\S*\s*$/u', '', $before));
    }

    private function wordEnd(): int
    {
        $word = preg_match('/^\s*\S*/u', $this->from($this->cursor), $match) === 1 ? $match[0] : '';

        return $this->cursor + mb_strlen($word);
    }

    private function before(int $offset): string
    {
        return mb_substr($this->text, 0, $offset);
    }

    private function from(int $offset): string
    {
        return mb_substr($this->text, $offset);
    }

    private function length(): int
    {
        return mb_strlen($this->text);
    }
}
