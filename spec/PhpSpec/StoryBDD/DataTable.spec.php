<?php

use PhpSpec\StoryBDD\DataTable;

describe(DataTable::class, function () {

    it("creates from raw rows with headers", function () {
        $table = new DataTable([
            ['name', 'age'],
            ['Alice', '30'],
            ['Bob', '25'],
        ]);
        expect($table->getHeaders())->toBe(['name', 'age']);
        expect($table)->toHaveCount(2);
    });

    it("returns rows as associative arrays", function () {
        $table = new DataTable([
            ['name', 'age'],
            ['Alice', '30'],
        ]);
        expect($table->getRows())->toBe([['name' => 'Alice', 'age' => '30']]);
    });

    it("supports array access by index", function () {
        $table = new DataTable([
            ['name'],
            ['Alice'],
            ['Bob'],
        ]);
        expect($table[0]['name'])->toBe('Alice');
        expect($table[1]['name'])->toBe('Bob');
        expect(isset($table[0]))->toBeTrue();
        expect(isset($table[5]))->toBeFalse();
    });

    it("is immutable via array access", function () {
        $table = new DataTable([['name'], ['Alice']]);
        expect(fn() => $table->offsetSet(0, 'x'))->toThrow(\LogicException::class);
        expect(fn() => $table->offsetUnset(0))->toThrow(\LogicException::class);
    });

    it("is iterable", function () {
        $table = new DataTable([
            ['name'],
            ['Alice'],
            ['Bob'],
        ]);
        $names = [];
        foreach ($table as $row) {
            $names[] = $row['name'];
        }
        expect($names)->toBe(['Alice', 'Bob']);
    });

    it("rewinds iterator correctly", function () {
        $table = new DataTable([
            ['name'],
            ['Alice'],
            ['Bob'],
        ]);
        // First iteration
        foreach ($table as $row) {}
        // Second iteration should work after rewind
        $names = [];
        foreach ($table as $row) {
            $names[] = $row['name'];
        }
        expect($names)->toBe(['Alice', 'Bob']);
    });

    it("returns a specific row", function () {
        $table = new DataTable([
            ['name', 'age'],
            ['Alice', '30'],
            ['Bob', '25'],
        ]);
        expect($table->getRow(1))->toBe(['name' => 'Bob', 'age' => '25']);
    });

    it("throws for invalid row index", function () {
        $table = new DataTable([['name'], ['Alice']]);
        expect(fn() => $table->getRow(99))->toThrow(\OutOfRangeException::class);
    });

    it("returns a column by name", function () {
        $table = new DataTable([
            ['name', 'age'],
            ['Alice', '30'],
            ['Bob', '25'],
        ]);
        expect($table->getColumn('name'))->toBe(['Alice', 'Bob']);
    });

    it("hydrates rows into objects", function () {
        $table = new DataTable([
            ['name', 'age'],
            ['Alice', '30'],
        ]);
        $objects = $table->asClass(\stdClass::class);
        expect($objects)->toHaveCount(1);
        expect($objects[0]->name)->toBe('Alice');
        expect($objects[0]->age)->toBe('30');
    });

    it("converts to plain array", function () {
        $table = new DataTable([
            ['x'],
            ['1'],
            ['2'],
        ]);
        expect($table->toArray())->toBe([
            ['x' => '1'],
            ['x' => '2'],
        ]);
    });

    it("exposes the iterator key", function () {
        $table = new DataTable([['name'], ['Alice'], ['Bob']]);
        $table->rewind();
        expect($table->key())->toBe(0);
        $table->next();
        expect($table->key())->toBe(1);
    });

    it("handles empty input", function () {
        $table = new DataTable([]);
        expect($table)->toHaveCount(0);
        expect($table->getHeaders())->toBe([]);
        expect($table->getRows())->toBe([]);
    });

});
