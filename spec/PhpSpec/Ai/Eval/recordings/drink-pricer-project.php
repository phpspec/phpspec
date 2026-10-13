<?php

// A pricer whose three primitives travel together, as it stood in the toy
// project before the live refactor sessions the evals replay.
return [
    'src/Brew/Money.php' => <<<'PHP'
        <?php

        namespace Brew;

        final class Money
        {
            public function __construct(
                private readonly int $pence,
            ) {
            }

            public function plus(self $other): self
            {
                return new self($this->pence + $other->pence);
            }
        }

        PHP,
    'src/Brew/DrinkPricer.php' => <<<'PHP'
        <?php

        namespace Brew;

        use InvalidArgumentException;

        final class DrinkPricer
        {
            public function priceOf(string $size, string $milk, int $shots): Money
            {
                $pence = match ($size) {
                    'small' => 250,
                    'medium' => 300,
                    'large' => 350,
                    default => throw new InvalidArgumentException(sprintf('Unknown size "%s".', $size)),
                };

                if ($milk === 'oat') {
                    $pence += 40;
                } elseif ($milk === 'almond') {
                    $pence += 50;
                } elseif ($milk === 'soy') {
                    $pence += 30;
                }

                if ($shots > 1) {
                    $pence += ($shots - 1) * 60;
                }

                return new Money($pence);
            }
        }

        PHP,
    'spec/Brew/DrinkPricer.spec.php' => <<<'PHP'
        <?php

        use Brew\DrinkPricer;
        use Brew\Money;

        describe(DrinkPricer::class, function () {
            this('pricer', fn () => new DrinkPricer());

            it('prices a drink by its size', function () {
                expect($this->pricer->priceOf('small', 'dairy', 1))->toBeLike(new Money(250));
                expect($this->pricer->priceOf('large', 'dairy', 1))->toBeLike(new Money(350));
            });

            it('charges for plant milk', function () {
                expect($this->pricer->priceOf('medium', 'oat', 1))->toBeLike(new Money(340));
                expect($this->pricer->priceOf('medium', 'almond', 1))->toBeLike(new Money(350));
                expect($this->pricer->priceOf('medium', 'soy', 1))->toBeLike(new Money(330));
            });

            it('charges for every shot after the first', function () {
                expect($this->pricer->priceOf('small', 'dairy', 3))->toBeLike(new Money(370));
            });

            it('refuses a size it does not sell', function () {
                expect(fn () => $this->pricer->priceOf('huge', 'dairy', 1))->toThrow(new InvalidArgumentException('Unknown size "huge".'));
            });
        });

        PHP,
];
