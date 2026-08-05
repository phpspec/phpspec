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

namespace PhpSpec\Offers;

/**
 * @internal
 * Something PhpSpec will do if you say yes, kept where you can say yes to it
 * later.
 *
 * A reader has to see an offer before accepting it, and what a model proposes
 * cannot be rediscovered by asking again, so an offer carries an identity and
 * everything needed to carry it out. It also remembers the file as it was when
 * it was made: accepting an offer that no longer fits the code would apply a
 * decision to something the reader never saw.
 */
final readonly class Offer
{
    /**
     * @param string $id how to refer to this offer
     * @param string $kind what sort of thing accepting it does
     * @param string $action what it does to the target: create or update
     * @param string $path the project-relative file it targets
     * @param string $content the complete content it would write
     * @param string $was the file's content when the offer was made, empty for a new file
     */
    private function __construct(
        public string $id,
        public string $kind,
        public string $action,
        public string $path,
        public string $content,
        public string $was,
    ) {}

    /**
     * An offer to write a file.
     *
     * @param string $path the project-relative path
     * @param string $content the complete proposed content
     * @param bool $isNew whether the file does not exist yet
     * @param string $was the current content, empty for a new file
     */
    public static function write(string $path, string $content, bool $isNew, string $was = ''): self
    {
        return new self(
            self::identify($path, $content),
            'write',
            $isNew ? 'create' : 'update',
            $path,
            $content,
            $was,
        );
    }

    /**
     * Whether the code has moved on since the offer was made, in which case
     * accepting it would apply a decision taken about something else.
     *
     * @param string|null $current the file's content now, or null when it is not there
     */
    public function staleAgainst(?string $current): bool
    {
        if ($this->action === 'create') {
            return $current !== null;
        }

        return $current === null || $current !== $this->was;
    }

    /**
     * @return array<string, string> the offer as it is stored
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'action' => $this->action,
            'path' => $this->path,
            'content' => $this->content,
            'was' => $this->was,
        ];
    }

    /**
     * @param array<string, mixed> $stored one offer as it was stored
     */
    public static function fromArray(array $stored): self
    {
        return new self(
            (string) ($stored['id'] ?? ''),
            (string) ($stored['kind'] ?? 'write'),
            (string) ($stored['action'] ?? 'update'),
            (string) ($stored['path'] ?? ''),
            (string) ($stored['content'] ?? ''),
            (string) ($stored['was'] ?? ''),
        );
    }

    /**
     * The identity of an offer, derived from what it would do rather than
     * invented, so an offer that is still on the table keeps the same id from
     * one run to the next and a reader can tell it apart from a new one.
     */
    private static function identify(string $path, string $content): string
    {
        return 'o_' . substr(sha1($path . "\0" . $content), 0, 8);
    }
}
