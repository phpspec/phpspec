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

use Closure;

/**
 * @internal
 * Something PhpSpec will do if you say yes, kept where you can say yes to it
 * later.
 *
 * Deciding requires seeing, and seeing happens in an earlier command than
 * accepting, so an offer carries an identity and everything needed to carry it
 * out. The identity is derived from what the offer would do rather than
 * invented, so an offer still on the table keeps its id from one run to the
 * next and two commands can name the same offer without agreeing in advance.
 *
 * Three kinds, one shape: a change to write, whose content travels with it, a
 * piece of code to generate, whose recipe does, and a refactoring plan to
 * carry out, whose steps do.
 */
final readonly class Offer
{
    public const WRITE = 'write';
    public const GENERATE = 'generate';
    public const REFACTOR = 'refactor';

    /**
     * @param string $id how to refer to this offer
     * @param string $kind write or generate
     * @param string $action what accepting does, in the reader's terms
     * @param string $target what it affects: a file path, a class, or an invocation
     * @param array<string, mixed> $data everything else the kind needs to be carried out
     * @param int|null $made when the offer was put on the table, as a Unix time; null until it is
     */
    private function __construct(
        public string $id,
        public string $kind,
        public string $action,
        public string $target,
        public array $data = [],
        public ?int $made = null,
    ) {}

    /**
     * The same offer, put on the table at this time.
     */
    public function madeAt(int $time): self
    {
        return new self($this->id, $this->kind, $this->action, $this->target, $this->data, $time);
    }

    /**
     * An offer to write a file, remembering the file as it stands so a stale
     * acceptance can be refused.
     *
     * @param string $path the project-relative path
     * @param string $content the complete proposed content
     * @param bool $isNew whether the file does not exist yet
     * @param string $was the current content, empty for a new file
     */
    public static function write(string $path, string $content, bool $isNew, string $was = ''): self
    {
        return new self(
            self::identify(self::WRITE, $path, $content),
            self::WRITE,
            $isNew ? 'create' : 'update',
            $path,
            ['content' => $content, 'was' => $was],
        );
    }

    /**
     * An offer to generate code PhpSpec knows how to write: a missing class, an
     * interface, a method, step definitions. The candidate travels with the
     * offer, so accepting generates exactly what was reported.
     *
     * @param string $action the generation action, e.g. create_class
     * @param string $target what would be generated
     * @param array<string, mixed> $candidates the serialised GenerationCandidates holding only this one
     */
    public static function generate(string $action, string $target, array $candidates): self
    {
        return new self(
            self::identify(self::GENERATE, $action, $target),
            self::GENERATE,
            $action,
            $target,
            ['candidates' => $candidates],
        );
    }

    /**
     * An offer to carry out a refactoring plan, made reading these files: a
     * plan about code that has since changed is about something else.
     *
     * @param string $target the class refactored, with the method when it named one
     * @param array<string, mixed> $plan the serialised plan
     * @param array<string, string> $read each file the plan was made reading, project-relative, with a sha1 of its content
     */
    public static function refactor(string $target, array $plan, array $read): self
    {
        return new self(
            self::identify(self::REFACTOR, $target, (string) json_encode($plan)),
            self::REFACTOR,
            'refactor',
            $target,
            ['plan' => $plan, 'read' => $read],
        );
    }

    /**
     * The serialised plan a refactoring offer would carry out, empty for
     * any other offer.
     *
     * @return array<string, mixed>
     */
    public function plan(): array
    {
        return is_array($this->data['plan'] ?? null) ? $this->data['plan'] : [];
    }

    /**
     * The complete content a write offer would put in the file.
     */
    public function content(): string
    {
        return is_string($this->data['content'] ?? null) ? $this->data['content'] : '';
    }

    /**
     * The file's content when a write offer was made, empty for a new file.
     */
    public function was(): string
    {
        return is_string($this->data['was'] ?? null) ? $this->data['was'] : '';
    }

    /**
     * The file that has moved on since the offer was made, in which case
     * accepting it would apply a decision taken about something else; null
     * when none has. Generated code never goes stale: the generators never
     * overwrite.
     *
     * @param Closure(string): (string|null) $contentOf a project-relative file's content now, null when it is not there
     */
    public function changedFile(Closure $contentOf): ?string
    {
        return match ($this->kind) {
            self::WRITE => $this->changedTarget($contentOf($this->target)),
            self::REFACTOR => $this->changedRead($contentOf),
            default => null,
        };
    }

    private function changedTarget(?string $current): ?string
    {
        $stale = $this->action === 'create' ? $current !== null : $current === null || $current !== $this->was();

        return $stale ? $this->target : null;
    }

    /**
     * @param Closure(string): (string|null) $contentOf
     */
    private function changedRead(Closure $contentOf): ?string
    {
        foreach (is_array($this->data['read'] ?? null) ? $this->data['read'] : [] as $path => $hash) {
            $current = $contentOf((string) $path);

            if ($current === null || sha1($current) !== $hash) {
                return (string) $path;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed> the offer as it is stored
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'action' => $this->action,
            'target' => $this->target,
            'data' => $this->data,
        ] + ($this->made === null ? [] : ['made' => $this->made]);
    }

    /**
     * @param array<string, mixed> $stored one offer as it was stored
     */
    public static function fromArray(array $stored): self
    {
        return new self(
            (string) ($stored['id'] ?? ''),
            (string) ($stored['kind'] ?? self::WRITE),
            (string) ($stored['action'] ?? 'update'),
            (string) ($stored['target'] ?? ''),
            is_array($stored['data'] ?? null) ? $stored['data'] : [],
            is_int($stored['made'] ?? null) ? $stored['made'] : null,
        );
    }

    /**
     * The identity of an offer, derived from what it would do.
     */
    private static function identify(string $kind, string $first, string $second): string
    {
        return 'o_' . substr(sha1($kind . "\0" . $first . "\0" . $second), 0, 8);
    }
}
