<?php

use PhpSpec\Console\Command\Pair\Key;
use PhpSpec\Console\Command\Pair\KeyName;
use PhpSpec\Console\Command\Pair\TypedLine;

describe(TypedLine::class, function () {

    $after = fn(TypedLine $line, KeyName $name, string $text = ''): TypedLine => $line->after(new Key($name, $text));

    it('starts with the cursor at the end of its text', function () {
        expect((new TypedLine('héllo'))->cursor)->toBe(5);
    });

    it('inserts typed text, a new line too, at the cursor', function () use ($after) {
        expect($after(new TypedLine('ac', 1), KeyName::Text, 'b'))->toBeLike(new TypedLine('abc', 2));
        expect($after(new TypedLine('ab', 1), KeyName::NewLine))->toBeLike(new TypedLine("a\nb", 2));
    });

    it('deletes before the cursor with Backspace and under it with Delete', function () use ($after) {
        expect($after(new TypedLine('abc', 2), KeyName::Backspace))->toBeLike(new TypedLine('ac', 1));
        expect($after(new TypedLine('abc', 0), KeyName::Backspace))->toBeLike(new TypedLine('abc', 0));
        expect($after(new TypedLine('abc', 1), KeyName::Delete))->toBeLike(new TypedLine('ac', 1));
        expect($after(new TypedLine('abc', 3), KeyName::Delete))->toBeLike(new TypedLine('abc', 3));
    });

    it('moves by a character, never past either end', function () use ($after) {
        expect($after(new TypedLine('ab', 1), KeyName::Left)->cursor)->toBe(0);
        expect($after(new TypedLine('ab', 0), KeyName::Left)->cursor)->toBe(0);
        expect($after(new TypedLine('ab', 1), KeyName::Right)->cursor)->toBe(2);
        expect($after(new TypedLine('ab', 2), KeyName::Right)->cursor)->toBe(2);
    });

    it('jumps to the start or end of the line the cursor is on', function () use ($after) {
        expect($after(new TypedLine("ab\ncd", 4), KeyName::Home)->cursor)->toBe(3);
        expect($after(new TypedLine("ab\ncd", 4), KeyName::End)->cursor)->toBe(5);
        expect($after(new TypedLine("ab\ncd", 1), KeyName::Home)->cursor)->toBe(0);
        expect($after(new TypedLine("ab\ncd", 1), KeyName::End)->cursor)->toBe(2);
    });

    it('moves by a word, to its start going left and past its end going right', function () use ($after) {
        expect($after(new TypedLine('say hello world', 15), KeyName::WordLeft)->cursor)->toBe(10);
        expect($after(new TypedLine('say hello world', 10), KeyName::WordLeft)->cursor)->toBe(4);
        expect($after(new TypedLine('say hello world', 0), KeyName::WordRight)->cursor)->toBe(3);
        expect($after(new TypedLine('say hello world', 3), KeyName::WordRight)->cursor)->toBe(9);
    });

    it('leaves the line as it is for a key that does not edit it', function () use ($after) {
        expect($after(new TypedLine('ab', 1), KeyName::Up))->toBeLike(new TypedLine('ab', 1));
    });
});
