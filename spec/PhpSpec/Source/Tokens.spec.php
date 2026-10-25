<?php

use PhpSpec\Source\Tokens;

describe(Tokens::class, function () {
    this('first', fn () => function (Tokens $tokens, string $text, int $from = 0): int {
        foreach ($tokens->list as $i => $token) {
            if ($i >= $from && $token->text === $text) {
                return $i;
            }
        }

        return -1;
    });

    it('finds where a body opens past its signature, or the semicolon a method without one ends on', function () {
        $tokens = Tokens::of('<?php function f(array $a = [1, 2]): int { return 1; } function g();');
        $f = ($this->first)($tokens, 'function');
        $g = ($this->first)($tokens, 'function', $f + 1);

        expect($tokens->list[$tokens->bodyOpen($f)]->text)->toBe('{');
        expect($tokens->list[$tokens->bodyOpen($g)]->text)->toBe(';');
    });

    it('finds the brace closing the one a body opens, over braces inside it', function () {
        $tokens = Tokens::of('<?php function f() { if (true) { echo "{$x}"; } } return 1;');
        $close = $tokens->matchingClose(($this->first)($tokens, '{'));

        expect($tokens->list[$close]->text)->toBe('}');
        expect($tokens->list[$tokens->significantAfter($close)]->text)->toBe('return');
    });

    it('ends an arrow function\'s expression before the separator or the bracket that ends it', function () {
        $tokens = Tokens::of('<?php f(fn ($x) => [$x, 1], 2);');

        expect($tokens->list[$tokens->arrowEnd(($this->first)($tokens, 'fn'))]->text)->toBe(']');
    });

    it('steps over whitespace and comments either side', function () {
        $tokens = Tokens::of('<?php a /* note */ b');
        $b = ($this->first)($tokens, 'b');

        expect($tokens->list[$tokens->significantBefore($b)]->text)->toBe('a');
        expect($tokens->list[$tokens->significantAfter(($this->first)($tokens, 'a'))]->text)->toBe('b');
        expect($tokens->significantAfter($b))->toBeNull();
        expect($tokens->significantBefore(0))->toBeNull();
    });
});
