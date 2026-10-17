<?php

use PhpSpec\Console\Command\Pair\InputArea;
use PhpSpec\Console\Command\Pair\WrappedInput;

describe(WrappedInput::class, function () {

    it('keeps a line that fits on one row, the cursor after it', function () {
        $input = new WrappedInput('❯ ', 'hi', null, 10);

        expect($input->rowCount())->toBe(1);
        expect($input->paintedIn(new InputArea(5, 1)))->toBe("\033[5;1H\033[K❯ hi\033[5;5H");
    });

    it('wraps a line wider than the terminal onto the rows below, the cursor on the last', function () {
        $input = new WrappedInput('❯ ', 'abcdefghij', null, 8);

        expect($input->rowCount())->toBe(2);
        expect($input->paintedIn(new InputArea(4, 2)))->toBe("\033[4;1H\033[K❯ abcdef\033[5;1H\033[Kghij\033[5;5H");
    });

    it('moves the cursor to a row of its own when the line fills the last one exactly', function () {
        $input = new WrappedInput('❯ ', 'abcdef', null, 8);

        expect($input->rowCount())->toBe(2);
        expect($input->paintedIn(new InputArea(4, 2)))->toBe("\033[4;1H\033[K❯ abcdef\033[5;1H\033[K\033[5;1H");
    });

    it('shows the last rows when the area holds fewer than the line takes', function () {
        $input = new WrappedInput('❯ ', 'abcdefgh', null, 4);

        expect($input->rowCount())->toBe(3);
        expect($input->paintedIn(new InputArea(7, 2)))->toBe("\033[7;1H\033[Kcdef\033[8;1H\033[Kgh\033[8;3H");
    });

    it('counts a wide character as the two columns it takes', function () {
        $input = new WrappedInput('❯ ', '日本語', null, 5);

        expect($input->paintedIn(new InputArea(1, 2)))->toBe("\033[1;1H\033[K❯ 日\033[2;1H\033[K本語\033[2;5H");
    });

    it('dims the ghost after the cursor, which stays before it', function () {
        $input = new WrappedInput('❯ ', '', '/next', 20);

        expect($input->paintedIn(new InputArea(3, 1)))->toBe("\033[3;1H\033[K❯ \033[2m/next\033[0m\033[3;3H");
    });

    it('paints in place on the current row when there is no screen to place it on', function () {
        expect((new WrappedInput('❯ ', 'hi', null, 10))->inPlace())->toBe("\r\033[K❯ hi");
        expect((new WrappedInput('❯ ', '', '/next', 10))->inPlace())->toBe("\r\033[K❯ \033[2m/next\033[0m\033[5D");
    });
});
