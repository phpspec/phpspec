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
use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;

/**
 * @internal
 * Where offers wait between being made and being taken.
 *
 * A reader accepts an offer in a later command than the one that made it, so
 * the offer has to outlive its own process, but not by much: an offer is a
 * decision about the project as it was read, and twenty minutes later, or a
 * project later in the same directory, it is about something else. The book
 * keeps the most recent ones for twenty minutes and nothing else: it is a
 * place to look something up by id, not a history.
 */
final class OfferBook
{
    /** How many offers stay on the table. Older ones are forgotten. */
    private const KEPT = 50;

    /** How long an offer stays on the table, in seconds: twenty minutes. */
    public const SHELF_LIFE = 1_200;

    private const PATH = '.phpspec/offers.json';

    private readonly Filesystem $filesystem;

    /** @var Closure(): int the time now, as a Unix time */
    private readonly Closure $now;

    /**
     * @param Closure(): int|null $now the clock, the system's when not given
     */
    public function __construct(?Filesystem $filesystem = null, private readonly ?string $baseDir = null, ?Closure $now = null)
    {
        $this->filesystem = $filesystem ?? new RealFilesystem();
        $this->now = $now ?? static fn(): int => time();
    }

    /**
     * Puts offers on the table, newest last, without recording the same offer
     * twice: an offer that is made again is the one that was already there,
     * made now.
     */
    public function record(Offer ...$offers): void
    {
        if ($offers === []) {
            return;
        }

        $book = $this->all();
        $taken = $this->takenRecently();
        $now = ($this->now)();

        foreach ($offers as $offer) {
            unset($book[$offer->id], $taken[$offer->id]);
            $book[$offer->id] = $offer->madeAt($now);
        }

        $this->store(array_slice($book, -self::KEPT, null, true), $taken);
    }

    /**
     * Takes the offer off the table, remembering it was taken, so a second
     * acceptance is told so rather than that the code has moved on.
     */
    public function taken(Offer $offer): void
    {
        $book = $this->all();
        $taken = $this->takenRecently();
        unset($book[$offer->id]);
        $taken[$offer->id] = ($this->now)();

        $this->store($book, $taken);
    }

    /**
     * Whether the offer with this id was taken within the shelf life.
     */
    public function wasTaken(string $id): bool
    {
        return isset($this->takenRecently()[$id]);
    }

    /**
     * The offer with this id, or null when the table never held it, has since
     * forgotten it, or it has expired.
     */
    public function find(string $id): ?Offer
    {
        return $this->all()[$id] ?? null;
    }

    /**
     * How long ago the offer with this id was made, in seconds, expired or
     * not; null once the book has been written without it, or when it never
     * held it. What tells a reader an id is stale apart from one that is
     * made up.
     */
    public function ageOf(string $id): ?int
    {
        $made = ($this->stored()[$id] ?? null)?->made;

        return $made === null ? null : ($this->now)() - $made;
    }

    /**
     * Every offer still on the table, oldest first, keyed by id. One made
     * longer ago than the shelf life, or never stamped, is off the table.
     *
     * @return array<string, Offer>
     */
    private function all(): array
    {
        $since = ($this->now)() - self::SHELF_LIFE;

        return array_filter($this->stored(), static fn(Offer $offer): bool => $offer->made !== null && $offer->made > $since);
    }

    /**
     * When each offer taken within the shelf life was taken, by id.
     *
     * @return array<string, int>
     */
    private function takenRecently(): array
    {
        $since = ($this->now)() - self::SHELF_LIFE;
        $taken = $this->document()['taken'] ?? [];

        return array_filter(is_array($taken) ? $taken : [], static fn(mixed $at): bool => is_int($at) && $at > $since);
    }

    /**
     * The book as it is on disk, empty when there is none or it is damaged.
     *
     * @return array<string, mixed>
     */
    private function document(): array
    {
        $path = $this->file();

        if (!$this->filesystem->exists($path)) {
            return [];
        }

        $stored = json_decode($this->filesystem->read($path), true);

        return is_array($stored) ? $stored : [];
    }

    /**
     * Every offer the book holds on disk, expired or not, keyed by id.
     *
     * @return array<string, Offer>
     */
    private function stored(): array
    {
        $stored = $this->document();

        if (!is_array($stored['offers'] ?? null)) {
            return [];
        }

        $offers = [];

        foreach ($stored['offers'] as $one) {
            if (is_array($one)) {
                $offer = Offer::fromArray($one);
                $offers[$offer->id] = $offer;
            }
        }

        return $offers;
    }

    /**
     * @param array<string, Offer> $offers
     * @param array<string, int> $taken
     */
    private function store(array $offers, array $taken = []): void
    {
        $path = $this->file();
        $dir = dirname($path);

        if (!$this->filesystem->exists($dir)) {
            $this->filesystem->mkdir($dir);
        }

        $document = [
            'v' => 1,
            'offers' => array_values(array_map(static fn(Offer $offer): array => $offer->toArray(), $offers)),
        ] + ($taken === [] ? [] : ['taken' => $taken]);

        $this->filesystem->write($path, (string) json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function file(): string
    {
        return ($this->baseDir ?? (getcwd() ?: '.')) . '/' . self::PATH;
    }
}
