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
 * what was typed, then the dim ghost suggestion. Characters are placed by the
 * columns they take, so a wide character never splits across rows, and the
 * cursor sits after what was typed, on a row of its own when the last row is
 * full.
 */
final readonly class WrappedInput
{
    private const DIM = "\033[2m";
    private const RESET = "\033[0m";

    /** @var list<list<array{string, bool}>> each row's characters, each marked whether it is the ghost's */
    private array $rows;

    private int $cursorRow;

    private int $cursorColumn;

    public function __construct(
        private string $prompt,
        private string $buffer,
        private ?string $ghost,
        int $width,
    ) {
        $rows = [[]];
        $column = 0;
        $place = static function (string $text, bool $isGhost) use (&$rows, &$column, $width): void {
            foreach (mb_str_split($text) as $character) {
                $takes = max(1, mb_strwidth($character));

                if ($column + $takes > $width) {
                    $rows[] = [];
                    $column = 0;
                }

                $rows[array_key_last($rows)][] = [$character, $isGhost];
                $column += $takes;
            }
        };

        $place($prompt . $buffer, false);

        if ($column >= $width) {
            $rows[] = [];
            $column = 0;
        }

        $this->cursorRow = array_key_last($rows);
        $this->cursorColumn = $column;

        $place((string) $ghost, true);
        $this->rows = $rows;
    }

    public function rowCount(): int
    {
        return count($this->rows);
    }

    /**
     * Paints the rows into the area, the last ones when it holds fewer than
     * the line takes, and leaves the cursor after what was typed.
     */
    public function paintedIn(InputArea $area): string
    {
        $first = min($this->cursorRow, max(0, $this->rowCount() - $area->rows));
        $painted = '';

        foreach (array_slice($this->rows, $first, $area->rows) as $offset => $row) {
            $painted .= sprintf("\033[%d;1H\033[K%s", $area->top + $offset, $this->drawn($row));
        }

        return $painted . sprintf("\033[%d;%dH", $area->top + $this->cursorRow - $first, $this->cursorColumn + 1);
    }

    /**
     * The line on the current row, for an output with no screen to place it
     * on: the ghost after the cursor, and the cursor moved back before it.
     */
    public function inPlace(): string
    {
        $line = "\r\033[K" . $this->prompt . $this->buffer;

        if ($this->ghost === null || $this->ghost === '') {
            return $line;
        }

        return $line . self::DIM . $this->ghost . self::RESET . "\033[" . mb_strlen($this->ghost) . 'D';
    }

    /**
     * @param list<array{string, bool}> $row
     */
    private function drawn(array $row): string
    {
        $drawn = '';
        $dim = false;

        foreach ($row as [$character, $isGhost]) {
            if ($isGhost !== $dim) {
                $drawn .= $isGhost ? self::DIM : self::RESET;
                $dim = $isGhost;
            }

            $drawn .= $character;
        }

        return $dim ? $drawn . self::RESET : $drawn;
    }
}
