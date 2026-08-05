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

use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;

/**
 * @internal
 * Where offers wait between being made and being taken.
 *
 * A reader accepts an offer in a later command than the one that made it, so
 * the offer has to outlive its own process. The book keeps the most recent ones
 * and nothing else: it is a place to look something up by id, not a history.
 */
final class OfferBook
{
    /** How many offers stay on the table. Older ones are forgotten. */
    private const KEPT = 50;

    private const PATH = '.phpspec/offers.json';

    private readonly Filesystem $filesystem;

    public function __construct(?Filesystem $filesystem = null, private readonly ?string $baseDir = null)
    {
        $this->filesystem = $filesystem ?? new RealFilesystem();
    }

    /**
     * Puts offers on the table, newest last, without recording the same offer
     * twice: an offer that is made again is the one that was already there.
     */
    public function record(Offer ...$offers): void
    {
        if ($offers === []) {
            return;
        }

        $book = $this->all();

        foreach ($offers as $offer) {
            unset($book[$offer->id]);
            $book[$offer->id] = $offer;
        }

        $this->store(array_slice($book, -self::KEPT, null, true));
    }

    /**
     * The offer with this id, or null when the table never held it or has since
     * forgotten it.
     */
    public function find(string $id): ?Offer
    {
        return $this->all()[$id] ?? null;
    }

    /**
     * Every offer still on the table, oldest first, keyed by id.
     *
     * @return array<string, Offer>
     */
    private function all(): array
    {
        $path = $this->file();

        if (!$this->filesystem->exists($path)) {
            return [];
        }

        $stored = json_decode($this->filesystem->read($path), true);

        if (!is_array($stored) || !is_array($stored['offers'] ?? null)) {
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
     */
    private function store(array $offers): void
    {
        $path = $this->file();
        $dir = dirname($path);

        if (!$this->filesystem->exists($dir)) {
            $this->filesystem->mkdir($dir);
        }

        $document = [
            'v' => 1,
            'offers' => array_values(array_map(static fn(Offer $offer): array => $offer->toArray(), $offers)),
        ];

        $this->filesystem->write($path, (string) json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function file(): string
    {
        return ($this->baseDir ?? (getcwd() ?: '.')) . '/' . self::PATH;
    }
}
