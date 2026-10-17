<?php

use PhpSpec\Console\Command\Pair\Key;
use PhpSpec\Console\Command\Pair\KeyDecoder;
use PhpSpec\Console\Command\Pair\KeyName;

describe(KeyDecoder::class, function () {

    this('decoder', fn() => new KeyDecoder());

    $reads = function (array $tokens, KeyName $name): void {
        foreach ($tokens as $token) {
            expect([$token, $this->decoder->decode($token)->name])->toBe([$token, $name]);
        }
    };

    it('reads Return as the end of the input', function () use ($reads) {
        $reads(["\r", "\n", "\033[13u"], KeyName::Enter);
    });

    it('reads Shift+Enter and Alt+Enter as a new line, however the terminal reports them', function () use ($reads) {
        $reads(["\033[13;2u", "\033[13;3u", "\033[27;2;13~", "\033[27;3;13~", "\033\r", "\033\n"], KeyName::NewLine);
    });

    it('reads the arrows, plain or in application mode', function () use ($reads) {
        $reads(["\033[D", "\033OD", "\033[1;2D"], KeyName::Left);
        $reads(["\033[C", "\033OC"], KeyName::Right);
        $reads(["\033[A", "\033OA"], KeyName::Up);
        $reads(["\033[B", "\033OB"], KeyName::Down);
    });

    it('reads Option+arrow, Ctrl+arrow and the Meta words as moves by a word', function () use ($reads) {
        $reads(["\033b", "\033[1;3D", "\033[1;9D", "\033[1;5D", "\033[98;3u"], KeyName::WordLeft);
        $reads(["\033f", "\033[1;3C", "\033[1;9C", "\033[1;5C", "\033[102;3u"], KeyName::WordRight);
    });

    it('reads Home and End in every spelling, Ctrl-A and Ctrl-E included', function () use ($reads) {
        $reads(["\033[H", "\033OH", "\033[1~", "\033[7~", "\001", "\033[97;5u"], KeyName::Home);
        $reads(["\033[F", "\033OF", "\033[4~", "\033[8~", "\005", "\033[101;5u"], KeyName::End);
    });

    it('reads Backspace, Delete and Tab', function () use ($reads) {
        $reads(["\177", "\010", "\033[127u"], KeyName::Backspace);
        $reads(["\033[3~"], KeyName::Delete);
        $reads(["\t", "\033[9u"], KeyName::Tab);
    });

    it('reads Esc and Ctrl-C as a cancel, and Ctrl-D as the end of input', function () use ($reads) {
        $reads(["\033", "\003", "\033[27u", "\033[99;5u"], KeyName::Cancel);
        $reads(["\004", "\033[100;5u"], KeyName::EndOfInput);
    });

    it('reads a character as text, a wide one or one the terminal reported by its code', function () {
        expect($this->decoder->decode('a'))->toBeLike(new Key(KeyName::Text, 'a'));
        expect($this->decoder->decode('é'))->toBeLike(new Key(KeyName::Text, 'é'));
        expect($this->decoder->decode("\033[65;2u"))->toBeLike(new Key(KeyName::Text, 'A'));
    });

    it('reads a paste as its text, its line breaks kept and its tabs made spaces', function () {
        expect($this->decoder->decode("\033[200~one\r\ntwo\rthree\tfour\033[201~"))
            ->toBeLike(new Key(KeyName::Text, "one\ntwo\nthree    four"));
    });

    it('ignores what it does not know', function () use ($reads) {
        $reads(["\033[Z", "\002", "\033[15~", "\033[1;2P"], KeyName::Ignored);
    });
});
