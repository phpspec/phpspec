<?php

use PhpSpec\Source\CodeUnits;

describe(CodeUnits::class, function () {
    it('tells each line by the one function, method, closure or arrow function that holds code on it, the file\'s own code apart', function () {
        $units = new CodeUnits(<<<'PHP'
            <?php

            function helper(): int
            {
                return 1;
            }

            final class Shelf
            {
                public function count(): int
                {
                    $double = function (int $x): int {
                        return $x * 2;
                    };
                    $plus = fn (int $y): int => $y + 1;

                    return $double(1);
                }
            }

            echo helper();
            PHP);

        expect($units->soleOwner(5))->toBe($units->soleOwner(3));
        expect($units->soleOwner(5))->not()->toBe(CodeUnits::FILE);
        expect($units->soleOwner(17))->not()->toBe($units->soleOwner(5));
        expect($units->soleOwner(13))->not()->toBe($units->soleOwner(17));
        expect($units->soleOwner(13))->not()->toBeNull();
        expect($units->soleOwner(21))->toBe(CodeUnits::FILE);
        expect($units->soleOwner(8))->toBe(CodeUnits::FILE);
    });

    it('takes a line holding code of more than one unit, or none, for no one\'s', function () {
        $units = new CodeUnits(<<<'PHP'
            <?php

            final class Shelf
            {
                public function count(): int
                {
                    $double = function (int $x): int {
                        return $x * 2;
                    };
                    $plus = fn (int $y): int => $y + 1;

                    return $double(1);
                }
            }
            PHP);

        expect($units->soleOwner(7))->toBeNull();
        expect($units->soleOwner(9))->toBeNull();
        expect($units->soleOwner(10))->toBeNull();
        expect($units->soleOwner(11))->toBeNull();
    });

    it('gives each property hook a unit of its own, a promoted property\'s too', function () {
        $units = new CodeUnits(<<<'PHP'
            <?php

            final class Label
            {
                public string $text {
                    get {
                        return 'text';
                    }
                    set (string $value) {
                        $this->text = $value;
                    }
                }

                public string $short = 'a' {
                    get => strtoupper($this->short);
                }

                public function __construct(
                    public string $name {
                        get => strtoupper($this->name);
                    },
                ) {
                    $this->text = 'text';
                }
            }
            PHP);

        $owners = [$units->soleOwner(7), $units->soleOwner(10), $units->soleOwner(15), $units->soleOwner(20), $units->soleOwner(23)];

        expect(array_unique(array_map('strval', $owners)))->toHaveCount(5);
        expect(in_array(CodeUnits::FILE, $owners, true) || in_array(null, $owners, true))->toBeFalse();
    });

    it('shares the line a hook starts on with the method that ends there', function () {
        $units = new CodeUnits(<<<'PHP'
            <?php

            final class Label
            {
                public function other(): string { return 'other'; } public string $text { get {
                    return 'text';
                } }
            }
            PHP);

        expect($units->soleOwner(5))->toBeNull();
        expect($units->soleOwner(6))->not()->toBeNull();
        expect($units->soleOwner(6))->not()->toBe(CodeUnits::FILE);
        expect($units->soleOwner(7))->toBeNull();
    });

    it('gives a property without hooks, and what its default holds, no hook unit', function () {
        $units = new CodeUnits(<<<'PHP'
            <?php

            final class Shelf
            {
                public array $items = [1, 2];
                public ?Closure $make = static function (): int {
                    return 1;
                };

                public function __construct(private int $size, public string $name = 'n')
                {
                    static $count = 0;
                    $count++;
                }

                public string $text {
                    #[Pure]
                    get => 'text';
                }
            }
            PHP);

        expect($units->soleOwner(5))->toBe(CodeUnits::FILE);
        expect($units->soleOwner(7))->not()->toBeNull();
        expect($units->soleOwner(7))->not()->toBe(CodeUnits::FILE);
        expect($units->soleOwner(13))->toBe($units->soleOwner(12));
        expect($units->soleOwner(12))->not()->toBe(CodeUnits::FILE);
        expect($units->soleOwner(17))->toBe(CodeUnits::FILE);
        expect($units->soleOwner(18))->not()->toBe(CodeUnits::FILE);
        expect($units->soleOwner(18))->not()->toBeNull();
    });

    it('reads a source cut short, as one changed mid-run, without failing', function () {
        $sources = ['<?php $x = 1;', '<?php function f() { return 1;', '<?php function f()', '<?php $f = fn () => 1', '<?php class A { public string $t { get', '<?php class A { public string $t { get => 1', '<?php class A { public string $t = 1', '<?php class A { public string $t { set ('];

        foreach ($sources as $source) {
            expect((new CodeUnits($source))->soleOwner(1))->not()->toBe(-1);
        }
    });

    it('gives a method without a body no unit', function () {
        $units = new CodeUnits(<<<'PHP'
            <?php

            interface Shelf
            {
                public function count(): int;
            }

            abstract class Base
            {
                abstract public function size(): int;
            }
            PHP);

        expect($units->soleOwner(5))->toBe(CodeUnits::FILE);
        expect($units->soleOwner(10))->toBe(CodeUnits::FILE);
    });

    it('reads a function imported by use as no unit', function () {
        $units = new CodeUnits(<<<'PHP'
            <?php

            use function strlen;

            echo strlen('a');
            PHP);

        expect($units->soleOwner(3))->toBe(CodeUnits::FILE);
        expect($units->soleOwner(5))->toBe(CodeUnits::FILE);
    });
});
