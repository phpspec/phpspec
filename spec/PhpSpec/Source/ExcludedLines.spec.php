<?php

use PhpSpec\Source\ExcludedLines;

describe(ExcludedLines::class, function () {

    context('a declaration', function () {
        it('is left out when marked ExcludeFromCoverage or @codeCoverageIgnore, from its keyword to its closing brace', function () {
            $source = <<<'PHP'
                <?php

                namespace App;

                use PhpSpec\ExcludeFromCoverage;

                #[ExcludeFromCoverage]
                final class Legacy
                {
                    public function convert(): string
                    {
                        return 'x';
                    }
                }

                class Clock
                {
                    /** @codeCoverageIgnore */
                    public function now(): int
                    {
                        return time();
                    }

                    public function today(): int
                    {
                        return 1;
                    }
                }
                PHP;

            $excluded = ExcludedLines::in($source);

            expect($excluded->lines())->toBe([8, 9, 10, 11, 12, 13, 14, 16, 17, 19, 20, 21, 22, 28]);
            expect($excluded->wholeFile())->toBeFalse();
        });

        it('reads the attribute qualified, or among others in its group', function () {
            $source = <<<'PHP'
                <?php
                namespace App;
                #[Deprecated, \PhpSpec\ExcludeFromCoverage]
                function shout(string $s): string
                {
                    return strtoupper($s);
                }
                function whisper(string $s): string
                {
                    return strtolower($s);
                }
                PHP;

            expect(ExcludedLines::in($source)->lines())->toBe([4, 5, 6, 7]);
        });

        it('is left out when the config names it, asked by its kind and full name', function () {
            $source = <<<'PHP'
                <?php
                namespace App;
                class Clock
                {
                    public function now(): int
                    {
                        return time();
                    }
                }
                function whisper(string $s): string
                {
                    return strtolower($s);
                }
                PHP;
            $asked = [];
            $named = function (string $kind, string $name) use (&$asked): bool {
                $asked[] = "$kind $name";

                return $name === 'App\Clock::now';
            };

            expect(ExcludedLines::in($source, $named)->lines())->toBe([3, 4, 5, 6, 7, 8]);
            expect($asked)->toBe(['class App\Clock', 'method App\Clock::now', 'function App\whisper']);
        });

        it('takes no anonymous class or ::class for a declaration', function () {
            $source = <<<'PHP'
                <?php
                $name = Foo::class;
                $object = new class {
                    public function y(): int
                    {
                        return 1;
                    }
                };
                PHP;

            $excluded = ExcludedLines::in($source, fn() => true);

            expect($excluded->lines())->toBe([]);
            expect($excluded->wholeFile())->toBeFalse();
        });

        it('reads on past a ::class to the declarations after it', function () {
            $source = <<<'PHP'
                <?php
                final class Pay
                {
                    public const GATE = Gate::class;

                    #[\PhpSpec\ExcludeFromCoverage]
                    public function legacy(): int
                    {
                        return 1;
                    }
                }
                PHP;

            expect(ExcludedLines::in($source)->lines())->toBe([2, 3, 7, 8, 9, 10, 11]);
        });
    });

    context('a line that only declares', function () {
        it('is left out: a class header, a namespaced constant and the end of the file', function () {
            $source = <<<'PHP'
                <?php
                namespace App;

                const VERSION = '1';

                define('READY', true);

                #[\AllowDynamicProperties]
                final class Tally extends Base implements
                    \Countable
                {
                    public function count(): int
                    {
                        return 1;
                    }
                }
                PHP;

            expect(ExcludedLines::in($source)->lines())->toBe([4, 9, 10, 11, 16]);
        });

        it('takes the end of the file as the line after the last one when a newline ends it', function () {
            expect(ExcludedLines::in("<?php\nclass A\n{\n}\n")->lines())->toBe([2, 3, 5]);
        });

        it('keeps a line that declares when it also holds code', function () {
            expect(ExcludedLines::in("<?php\nclass A { public function value() { return 1; } }")->lines())->toBe([]);
            expect(ExcludedLines::in("<?php\nclass A { public function value() { return 1; } }\n")->lines())->toBe([3]);
            expect(ExcludedLines::in("<?php\nclass A\n{\n    public function value() { return 1; }}")->lines())->toBe([2, 3]);
            expect(ExcludedLines::in("<?php\nfinal class A extends B implements\n    C { public function f() { return 1; }\n}\n")->lines())->toBe([2, 5]);
            expect(ExcludedLines::in("<?php\nnamespace App; const A = 1; echo A;\n")->lines())->toBe([3]);
        });

        it('keeps a last line that ends a function or a statement', function () {
            expect(ExcludedLines::in("<?php\nfunction f(): void\n{\n    echo 1;\n}")->lines())->toBe([]);
            expect(ExcludedLines::in("<?php\n\$ready = true;")->lines())->toBe([]);
        });
    });

    context('an interface', function () {
        it('never counts, and a file declaring only interfaces is left out whole', function () {
            $source = <<<'PHP'
                <?php
                namespace App;
                interface Pricing
                {
                    public function add(int $a, int $b): int;
                }
                PHP;

            $excluded = ExcludedLines::in($source);

            expect($excluded->lines())->toBe([3, 4, 5, 6]);
            expect($excluded->wholeFile())->toBeTrue();
        });
    });

    context('the whole file', function () {
        it('goes when everything it declares is left out and it runs nothing of its own', function () {
            expect(ExcludedLines::in("<?php\nnamespace App;\nuse X;\n#[\\PhpSpec\\ExcludeFromCoverage]\nclass A\n{\n    public function f(): int\n    {\n        return 1;\n    }\n}\n")->wholeFile())->toBeTrue();
            expect(ExcludedLines::in("<?php\nnamespace App;\n#[\\PhpSpec\\ExcludeFromCoverage]\nclass A\n{\n}\n\$x = 1;\n")->wholeFile())->toBeFalse();
            expect(ExcludedLines::in("<?php\nnamespace App;\nclass A\n{\n    public function f(): int\n    {\n        return 1;\n    }\n}\n")->wholeFile())->toBeFalse();
        });

        it('goes when it holds no code at all, as a class with an empty body', function () {
            expect(ExcludedLines::in("<?php\nnamespace App;\nfinal class Refused extends \\RuntimeException {}\n")->wholeFile())->toBeTrue();
            expect(ExcludedLines::in("<?php\nnamespace App;\nenum Size\n{\n    case Small;\n}\n")->wholeFile())->toBeTrue();
        });

        it('stays when a class holds code outside its methods: a property hook, or a closure in a constant', function () {
            expect(ExcludedLines::in("<?php\nclass HookOnly\n{\n    public string \$name {\n        get => 'Ann';\n    }\n}\n")->wholeFile())->toBeFalse();
            expect(ExcludedLines::in("<?php\nclass Doubled\n{\n    public int \$n = 0 {\n        set(int \$v) {\n            \$this->n = \$v * 2;\n        }\n    }\n}\n")->wholeFile())->toBeFalse();
            expect(ExcludedLines::in("<?php\nclass Twice\n{\n    public const F = static function (int \$x): int {\n        return \$x * 2;\n    };\n}\n")->wholeFile())->toBeFalse();
            expect(ExcludedLines::in("<?php\nclass Thrice\n{\n    public const F = [static fn (int \$x): int => \$x * 3];\n}\n")->wholeFile())->toBeFalse();
        });

        it('goes when a class only adapts the traits it uses', function () {
            expect(ExcludedLines::in("<?php\nclass Adapted\n{\n    use A, B {\n        A::f insteadof B;\n    }\n}\n")->wholeFile())->toBeTrue();
        });
    });

    context('a comment tag', function () {
        it('leaves out the line it ends, the line below it, the block a line opens, and a stretch', function () {
            $source = <<<'PHP'
                <?php
                namespace App;
                class Calculator
                {
                    public function divide(int $a, int $b): int
                    {
                        if ($b === 0) { // @codeCoverageIgnore
                            throw new \DivisionByZeroError('no');
                        }
                        if ($a < 0) {
                            // @codeCoverageIgnore
                            return -1;
                        }
                        // @codeCoverageIgnoreStart
                        if ($a > 1000) {
                            return 1000;
                        }
                        // @codeCoverageIgnoreEnd
                        try {
                            $r = intdiv($a, $b);
                        } catch (\Throwable $e) { // @codeCoverageIgnore
                            $r = 0;
                        }
                        return $r; // @codeCoverageIgnore
                    }
                }
                PHP;

            expect(ExcludedLines::in($source)->lines())->toBe([3, 4, 7, 8, 9, 12, 14, 15, 16, 17, 18, 21, 22, 23, 24, 26]);
        });

        it('takes a statement whole when it spans lines, a case up to the next, and an unclosed stretch to the end', function () {
            $source = <<<'PHP'
                <?php
                function check(int $a, int $b): int
                {
                    // @codeCoverageIgnore
                    if ($a > 0
                        && $b > 0) {
                        return 1;
                    }
                    switch ($a) {
                        case 1: // @codeCoverageIgnore
                            $a++;
                            break;
                        case 2:
                            return 2;
                    }
                    // @codeCoverageIgnoreStart
                    return 0;
                }
                PHP;

            expect(ExcludedLines::in($source)->lines())->toBe([5, 6, 7, 8, 10, 11, 12, 16, 17, 18]);
        });
    });
});
