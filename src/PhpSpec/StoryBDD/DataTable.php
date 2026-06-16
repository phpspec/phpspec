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

use ArrayAccess;
use Countable;
use Iterator;
use LogicException;
use OutOfRangeException;

/**
 * Represents an immutable Gherkin data table as rows of associative arrays keyed by header names.
 * The first raw row is treated as headers; subsequent rows become data keyed by those headers.
 */
/**
 * @implements ArrayAccess<int, array<string, string>>
 * @implements Iterator<int, array<string, string>>
 */
final class DataTable implements ArrayAccess, Iterator, Countable
{
    /** @var string[] column header names from the first row */
    private array $headers;
    /** @var array<int, array<string, string>> data rows keyed by header names */
    private array $rows;
    /** @var int current iterator position */
    private int $position = 0;

    /**
     * Builds the table from raw rows where the first row defines headers.
     *
     * @param array<int, array<int, string>> $rawRows array of arrays; first element is headers, rest are data rows
     */
    public function __construct(array $rawRows)
    {
        if (empty($rawRows)) {
            $this->headers = [];
            $this->rows = [];
            return;
        }

        $this->headers = array_shift($rawRows);
        $this->rows = array_map(function (array $row) {
            return array_combine($this->headers, $row);
        }, $rawRows);
    }

    /**
     * Returns the column header names.
     *
     * @return string[]
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Returns all data rows as associative arrays keyed by header names.
     *
     * @return array<int, array<string, string>>
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * Returns a single row by zero-based index.
     *
     * @param int $index the row index
     * @return array<string, string> the row as an associative array keyed by header names
     * @throws OutOfRangeException if the index does not exist
     */
    public function getRow(int $index): array
    {
        return $this->rows[$index] ?? throw new OutOfRangeException("Row {$index} does not exist");
    }

    /**
     * Returns all values from a single column by header name.
     *
     * @param string $name the column header name
     * @return string[] the column values across all rows
     */
    public function getColumn(string $name): array
    {
        return array_column($this->rows, $name);
    }

    /**
     * Hydrates each row into an object of the given class by setting public properties.
     *
     * @param string $className the fully qualified class name to instantiate
     * @return object[] array of hydrated objects
     */
    public function asClass(string $className): array
    {
        return array_map(function (array $row) use ($className) {
            $obj = new $className();
            foreach ($row as $key => $value) {
                $obj->$key = $value;
            }
            return $obj;
        }, $this->rows);
    }

    /**
     * Returns all rows as a plain array of associative arrays.
     *
     * @return array<int, array<string, string>>
     */
    public function toArray(): array
    {
        return $this->rows;
    }

    // ArrayAccess

    /**
     * Checks whether a row exists at the given offset.
     *
     * @param mixed $offset the row index to check
     * @return bool true if the row exists
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->rows[$offset]);
    }

    /**
     * Returns the row at the given offset, or null if it does not exist.
     *
     * @param mixed $offset the row index
     * @return array<string, string>|null the row data or null
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->rows[$offset] ?? null;
    }

    /**
     * Prevents setting a row value since DataTable is immutable.
     *
     * @param mixed $offset the row index
     * @param mixed $value the value to set
     * @return void
     * @throws LogicException always; DataTable is immutable
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('DataTable is immutable');
    }

    /**
     * Prevents unsetting a row since DataTable is immutable.
     *
     * @param mixed $offset the row index to unset
     * @return void
     * @throws LogicException always; DataTable is immutable
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('DataTable is immutable');
    }

    // Iterator

    /**
     * Returns the current row in the iteration.
     *
     * @return array<string, string> the current row keyed by header names
     */
    public function current(): array
    {
        return $this->rows[$this->position];
    }

    /**
     * Returns the current iterator position.
     *
     * @return int the zero-based row index
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * Advances the iterator to the next row.
     *
     * @return void
     */
    public function next(): void
    {
        $this->position++;
    }

    /**
     * Resets the iterator to the first row.
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Checks whether the current iterator position points to a valid row.
     *
     * @return bool true if the current position is valid
     */
    public function valid(): bool
    {
        return isset($this->rows[$this->position]);
    }

    // Countable

    /**
     * Returns the number of data rows (excluding the header row).
     *
     * @return int the row count
     */
    public function count(): int
    {
        return count($this->rows);
    }
}
