<?php

use PhpSpec\Console\Command\Pair\InputArea;
use PhpSpec\Console\Command\Pair\WrappedInput;

describe(WrappedInput::class, function () {

    it('keeps a line that fits on one row, the cursor after it', function () {
        $input = new WrappedInput('❯ ', 'hi', null, 10);

        expect($input->rowCount())->toBe(1);
        expect($input->paintedIn(new InputArea(5, 1)))->toBe("\033[5;1H\033[K❯ hi\033[5;5H");
    });

    it('wraps a line wider than the terminal onto the rows below, under the prompt, the cursor on the last', function () {
        $input = new WrappedInput('❯ ', 'abcdefghij', null, 8);

        expect($input->rowCount())->toBe(2);
        expect($input->paintedIn(new InputArea(4, 2)))->toBe("\033[4;1H\033[K❯ abcdef\033[5;1H\033[K  ghij\033[5;7H");
    });

    it('moves the cursor to a row of its own when the line fills the last one exactly', function () {
        $input = new WrappedInput('❯ ', 'abcdef', null, 8);

        expect($input->rowCount())->toBe(2);
        expect($input->paintedIn(new InputArea(4, 2)))->toBe("\033[4;1H\033[K❯ abcdef\033[5;1H\033[K  \033[5;3H");
    });

    it('shows the last rows when the area holds fewer than the line takes', function () {
        $input = new WrappedInput('❯ ', 'abcdefgh', null, 6);

        expect($input->rowCount())->toBe(3);
        expect($input->paintedIn(new InputArea(7, 2)))->toBe("\033[7;1H\033[K  efgh\033[8;1H\033[K  \033[8;3H");
    });

    it('counts a wide character as the two columns it takes', function () {
        $input = new WrappedInput('❯ ', '日本語', null, 6);

        expect($input->paintedIn(new InputArea(1, 2)))->toBe("\033[1;1H\033[K❯ 日本\033[2;1H\033[K  語\033[2;5H");
    });

    it('dims the ghost after the cursor, which stays before it', function () {
        $input = new WrappedInput('❯ ', '', '/next', 20);

        expect($input->paintedIn(new InputArea(3, 1)))->toBe("\033[3;1H\033[K❯ \033[2m/next\033[0m\033[3;3H");
    });

    it('places the cursor where it is in the text, not only at its end', function () {
        $input = new WrappedInput('❯ ', 'abcdefghij', null, 8, 7);

        expect($input->paintedIn(new InputArea(4, 2)))->toBe("\033[4;1H\033[K❯ abcdef\033[5;1H\033[K  ghij\033[5;4H");
    });

    it('starts a row at each line break', function () {
        $input = new WrappedInput('❯ ', "one\ntwo", null, 20);

        expect($input->rowCount())->toBe(2);
        expect($input->paintedIn(new InputArea(4, 2)))->toBe("\033[4;1H\033[K❯ one\033[5;1H\033[K  two\033[5;6H");
    });

    it('finds the place in the row above and below, keeping the column where it can', function () {
        $wrapped = fn(int $cursor) => new WrappedInput('❯ ', "first line\nab\nthird line", null, 40, $cursor);

        expect($wrapped(17)->cursorAbove())->toBe(13);
        expect($wrapped(17)->cursorBelow())->toBe(null);
        expect($wrapped(8)->cursorBelow())->toBe(13);
        expect($wrapped(12)->cursorAbove())->toBe(1);
        expect($wrapped(8)->cursorAbove())->toBe(null);
    });

    it('moves between the rows a long line wraps onto', function () {
        $input = new WrappedInput('❯ ', 'abcdefghij', null, 8, 9);

        expect($input->cursorAbove())->toBe(3);
        expect((new WrappedInput('❯ ', 'abcdefghij', null, 8, 3))->cursorBelow())->toBe(9);
    });

    it('paints in place on the current row when there is no screen to place it on', function () {
        expect((new WrappedInput('❯ ', 'hi', null, 10))->inPlace())->toBe("\r\033[K❯ hi");
        expect((new WrappedInput('❯ ', 'hello', null, 10, 2))->inPlace())->toBe("\r\033[K❯ hello\033[3D");
        expect((new WrappedInput('❯ ', '', '/next', 10))->inPlace())->toBe("\r\033[K❯ \033[2m/next\033[0m\033[5D");
    });
});
