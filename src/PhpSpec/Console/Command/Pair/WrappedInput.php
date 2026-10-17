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
 * A line being typed, laid out in rows as wide as the terminal: the prompt,
 * what was typed, then the dim ghost suggestion. A line break starts a row,
 * every row after the first is indented under the prompt so the text lines up,
 * and characters are placed by the columns they take, so a wide character
 * never splits across rows. The cursor is placed where it is in the text, on a
 * row of its own when it is at the end of a full one.
 */
final readonly class WrappedInput
{
    private const DIM = "\033[2m";
    private const RESET = "\033[0m";

    /** @var list<list<array{string, bool}>> each row's characters, each marked whether it is the ghost's */
    private array $rows;

    /** @var array<int, array{int, int}> the row and column of each place the cursor can be in the text */
    private array $places;

    private int $cursor;

    /**
     * @param int|null $cursor where the cursor is in the text, in characters; at its end when null
     */
    public function __construct(
        private string $prompt,
        private string $text,
        private ?string $ghost,
        int $width,
        ?int $cursor = null,
    ) {
        $indent = min(mb_strwidth($prompt), max(0, $width - 1));
        $characters = mb_str_split($text);
        $this->cursor = min(count($characters), max(0, $cursor ?? count($characters)));

        $rows = [[]];
        $column = 0;
        $places = [];
        $newRow = static function () use (&$rows, &$column, $indent): void {
            $rows[] = array_fill(0, $indent, [' ', false]);
            $column = $indent;
        };
        $place = static function (string $character, bool $isGhost) use (&$rows, &$column, $width, $newRow): void {
            $takes = max(1, mb_strwidth($character));

            if ($column + $takes > $width) {
                $newRow();
            }

            $rows[array_key_last($rows)][] = [$character, $isGhost];
            $column += $takes;
        };

        foreach (mb_str_split($prompt) as $character) {
            $place($character, false);
        }

        foreach ($characters as $offset => $character) {
            if ($character === "\n") {
                $places[$offset] = [array_key_last($rows), min($column, $width - 1)];
                $newRow();

                continue;
            }

            if ($column + max(1, mb_strwidth($character)) > $width) {
                $newRow();
            }

            $places[$offset] = [array_key_last($rows), $column];
            $place($character, false);
        }

        if ($column >= $width) {
            $newRow();
        }

        $places[count($characters)] = [array_key_last($rows), $column];

        foreach (mb_str_split((string) $ghost) as $character) {
            $place($character, true);
        }

        $this->rows = $rows;
        $this->places = $places;
    }

    public function rowCount(): int
    {
        return count($this->rows);
    }

    /**
     * Where the cursor lands one row up, as near its column as that row
     * allows; null on the first row.
     */
    public function cursorAbove(): ?int
    {
        return $this->placeOnRow($this->places[$this->cursor][0] - 1);
    }

    /**
     * Where the cursor lands one row down, as near its column as that row
     * allows; null on the last row of the text.
     */
    public function cursorBelow(): ?int
    {
        return $this->placeOnRow($this->places[$this->cursor][0] + 1);
    }

    /**
     * Paints the rows into the area, the last ones when it holds fewer than
     * the line takes, and leaves the cursor where it is in the text.
     */
    public function paintedIn(InputArea $area): string
    {
        [$row, $column] = $this->places[$this->cursor];
        $first = min($row, max(0, $this->rowCount() - $area->rows));
        $painted = '';

        foreach (array_slice($this->rows, $first, $area->rows) as $offset => $cells) {
            $painted .= sprintf("\033[%d;1H\033[K%s", $area->top + $offset, $this->drawn($cells));
        }

        return $painted . sprintf("\033[%d;%dH", $area->top + $row - $first, $column + 1);
    }

    /**
     * The line on the current row, for an output with no screen to place it
     * on: line breaks shown as a mark, the ghost after the text, and the
     * cursor moved back to where it is.
     */
    public function inPlace(): string
    {
        $shown = static fn(string $text): string => str_replace("\n", '⏎', $text);
        $after = $shown(mb_substr($this->text, $this->cursor));
        $line = "\r\033[K" . $this->prompt . $shown($this->text);
        $back = mb_strwidth($after);

        if ($this->ghost !== null && $this->ghost !== '') {
            $line .= self::DIM . $this->ghost . self::RESET;
            $back += mb_strwidth($this->ghost);
        }

        return $back === 0 ? $line : $line . "\033[" . $back . 'D';
    }

    /**
     * The place on a row nearest the cursor's column without passing it, or
     * the row's first place when every one is past it.
     */
    private function placeOnRow(int $row): ?int
    {
        $column = $this->places[$this->cursor][1];
        $onRow = array_filter($this->places, static fn(array $place): bool => $place[0] === $row);

        if ($onRow === []) {
            return null;
        }

        $reachable = array_filter($onRow, static fn(array $place): bool => $place[1] <= $column);

        return $reachable === [] ? min(array_keys($onRow)) : max(array_keys($reachable));
    }

    /**
     * @param list<array{string, bool}> $cells
     */
    private function drawn(array $cells): string
    {
        $drawn = '';
        $dim = false;

        foreach ($cells as [$character, $isGhost]) {
            if ($isGhost !== $dim) {
                $drawn .= $isGhost ? self::DIM : self::RESET;
                $dim = $isGhost;
            }

            $drawn .= $character;
        }

        return $dim ? $drawn . self::RESET : $drawn;
    }
}
