<?php

use PhpSpec\Offers\Offer;

describe(Offer::class, function () {

    it('identifies itself from what it would do, so the same offer keeps its id', function () {
        $one = Offer::write('src/App/Basket.php', "<?php\nclass Basket {}", true, '');
        $again = Offer::write('src/App/Basket.php', "<?php\nclass Basket {}", true, '');

        expect($one->id)->toBe($again->id);
        expect($one->id)->toStartWith('o_');
    });

    it('is a different offer when the content differs', function () {
        $one = Offer::write('src/App/Basket.php', "<?php\nclass Basket {}", true, '');
        $other = Offer::write('src/App/Basket.php', "<?php\nfinal class Basket {}", true, '');

        expect($one->id)->not()->toBe($other->id);
    });

    it('is a different offer when the path differs', function () {
        $one = Offer::write('src/App/Basket.php', '<?php', true, '');
        $other = Offer::write('src/App/Cart.php', '<?php', true, '');

        expect($one->id)->not()->toBe($other->id);
    });

    it('says what it would do to the file', function () {
        expect(Offer::write('src/App/Basket.php', '<?php', true, '')->action)->toBe('create');
        expect(Offer::write('src/App/Basket.php', '<?php', false, 'old')->action)->toBe('update');
    });

    it('survives a round trip through storage', function () {
        $offer = Offer::write('src/App/Basket.php', "<?php\nclass Basket {}", false, 'the old content');

        expect(Offer::fromArray($offer->toArray())->toArray())->toBe($offer->toArray());
    });

    it('remembers when it was made, through storage, and nothing before it is made', function () {
        $offer = Offer::write('src/App/Basket.php', '<?php', true, '');

        expect($offer->made)->toBeNull();
        expect($offer->madeAt(1_700_000_000)->made)->toBe(1_700_000_000);
        expect(Offer::fromArray($offer->madeAt(1_700_000_000)->toArray())->made)->toBe(1_700_000_000);
        expect($offer->madeAt(1_700_000_000)->id)->toBe($offer->id);
    });

    context('staleness', function () {
        this('content', fn() => fn(array $files): Closure => fn(string $path): ?string => $files[$path] ?? null);

        it('stands while the file is as it was when offered', function () {
            $offer = Offer::write('src/App/Basket.php', '<?php new', false, 'the old content');

            expect($offer->changedFile(($this->content)(['src/App/Basket.php' => 'the old content'])))->toBeNull();
        });

        it('names the file once it has moved on', function () {
            $offer = Offer::write('src/App/Basket.php', '<?php new', false, 'the old content');

            expect($offer->changedFile(($this->content)(['src/App/Basket.php' => 'someone edited it'])))->toBe('src/App/Basket.php');
        });

        it('names the file when what it would create is already there', function () {
            $offer = Offer::write('src/App/Basket.php', '<?php new', true, '');

            expect($offer->changedFile(($this->content)(['src/App/Basket.php' => 'something exists now'])))->toBe('src/App/Basket.php');
            expect($offer->changedFile(($this->content)([])))->toBeNull();
        });

        it('names the file when what it would update has gone', function () {
            $offer = Offer::write('src/App/Basket.php', '<?php new', false, 'the old content');

            expect($offer->changedFile(($this->content)([])))->toBe('src/App/Basket.php');
        });

        it('never goes stale when it generates code, the generators never overwriting', function () {
            expect(Offer::generate('create_class', 'App\\Basket', [])->changedFile(($this->content)([])))->toBeNull();
        });
    });

    context('a refactoring plan', function () {
        this('plan', fn() => ['technique' => 'Extract Class', 'rationale' => 'Till prints its own receipt.', 'steps' => [['title' => 'Describe Printer', 'doing' => 'Describing Printer']]]);
        this('read', fn() => ['src/App/Till.php' => sha1('<?php till'), 'spec/App/Till.spec.php' => sha1('<?php spec')]);
        this('offer', fn() => Offer::refactor('App\\Till', $this->plan, $this->read));

        it('is offered under its target, identified by the plan', function () {
            $other = Offer::refactor('App\\Till', ['technique' => 'Inline Class'] + $this->plan, $this->read);

            expect($this->offer->kind)->toBe(Offer::REFACTOR);
            expect($this->offer->action)->toBe('refactor');
            expect($this->offer->target)->toBe('App\\Till');
            expect($this->offer->id)->toStartWith('o_');
            expect($this->offer->id)->not()->toBe($other->id);
            expect(Offer::fromArray($this->offer->toArray())->toArray())->toBe($this->offer->toArray());
        });

        it('stands while every file it was made reading is as it was, and names the first that changed or went', function () {
            $as = fn(array $files): Closure => fn(string $path): ?string => $files[$path] ?? null;

            expect($this->offer->changedFile($as(['src/App/Till.php' => '<?php till', 'spec/App/Till.spec.php' => '<?php spec'])))->toBeNull();
            expect($this->offer->changedFile($as(['src/App/Till.php' => '<?php till', 'spec/App/Till.spec.php' => '<?php edited'])))->toBe('spec/App/Till.spec.php');
            expect($this->offer->changedFile($as(['spec/App/Till.spec.php' => '<?php spec'])))->toBe('src/App/Till.php');
        });

        it('carries the plan', function () {
            expect($this->offer->plan())->toBe($this->plan);
            expect(Offer::write('src/App/Till.php', '<?php', true)->plan())->toBe([]);
        });
    });
});
