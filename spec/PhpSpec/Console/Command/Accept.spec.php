<?php

use PhpSpec\Console\Command\Accept;
use PhpSpec\Filesystem;
use PhpSpec\Offers\Offer;
use PhpSpec\Offers\OfferBook;
use Symfony\Component\Console\Tester\CommandTester;

describe(Accept::class, function () {

    beforeEach(function (Filesystem $fs) {
        $this->files = [];
        $this->written = [];
        allow($fs->exists())->toReturnUsing(fn(string $path): bool => isset($this->files[$path]));
        allow($fs->read())->toReturnUsing(fn(string $path): string => $this->files[$path] ?? '');
        allow($fs->mkdir())->toReturn(null);
        // The book keeps itself on disk, so a write to .phpspec is bookkeeping
        // rather than a change to the project: assert on the project's files.
        allow($fs->write())->toReturnUsing(function (string $path, string $content) {
            $this->files[$path] = $content;
            if (!str_contains($path, '/.phpspec/')) {
                $this->written[$path] = $content;
            }
        });

        $this->book = new OfferBook($fs, '/project');
        $this->tester = fn(): CommandTester => new CommandTester(new Accept($fs, $this->book, '/project'));
    });

    it('takes an offer that is still on the table', function () {
        $offer = Offer::write('src/App/Basket.php', "<?php\nclass Basket {}", true, '');
        $this->book->record($offer);

        $tester = ($this->tester)();
        $tester->execute(['offer' => [$offer->id]], ['interactive' => false]);

        expect($tester->getStatusCode())->toBe(0);
        expect($tester->getDisplay())->toContain('Created src/App/Basket.php');
        expect($this->written['/project/src/App/Basket.php'] ?? '')->toContain('class Basket');
    });

    it('takes several offers at once', function () {
        $first = Offer::write('src/App/Basket.php', '<?php', true, '');
        $second = Offer::write('src/App/Cart.php', '<?php', true, '');
        $this->book->record($first, $second);

        $tester = ($this->tester)();
        $tester->execute(['offer' => [$first->id, $second->id]], ['interactive' => false]);

        expect($tester->getStatusCode())->toBe(0);
        expect(array_keys($this->written))->toBe(['/project/src/App/Basket.php', '/project/src/App/Cart.php']);
    });

    it('refuses an id it has never heard of, and says how offers are made', function () {
        $tester = ($this->tester)();
        $tester->execute(['offer' => ['o_nothing']], ['interactive' => false]);

        expect($tester->getStatusCode())->toBe(1);
        expect($tester->getDisplay())->toContain('o_nothing');
        expect($this->written)->toBe([]);
    });

    it('refuses an offer the code has moved past', function () {
        $offer = Offer::write('src/App/Basket.php', '<?php the proposal', false, 'the content when offered');
        $this->book->record($offer);
        $this->files['/project/src/App/Basket.php'] = 'someone edited it since';

        $tester = ($this->tester)();
        $tester->execute(['offer' => [$offer->id]], ['interactive' => false]);

        expect($tester->getStatusCode())->toBe(1);
        expect($tester->getDisplay())->toContain('changed since');
        expect($this->written)->toBe([]);
    });

    it('applies nothing at all when one of several offers is refused', function () {
        $good = Offer::write('src/App/Basket.php', '<?php', true, '');
        $this->book->record($good);

        $tester = ($this->tester)();
        $tester->execute(['offer' => [$good->id, 'o_nothing']], ['interactive' => false]);

        expect($tester->getStatusCode())->toBe(1);
        expect($this->written)->toBe([]);
    });

    it('answers an agent on its own channel', function () {
        $offer = Offer::write('src/App/Basket.php', '<?php', true, '');
        $this->book->record($offer);

        $tester = ($this->tester)();
        $tester->execute(['offer' => [$offer->id], '--format' => 'agent'], ['interactive' => false]);

        $document = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
        expect($document['action'])->toBe('accept');
        expect($document['accepted'][0]['id'])->toBe($offer->id);
        expect($document['accepted'][0]['path'])->toBe('src/App/Basket.php');
        expect($document['accepted'][0]['applied'])->toBeTrue();
    });

    it('tells an agent why it refused, on the same channel', function () {
        $tester = ($this->tester)();
        $tester->execute(['offer' => ['o_nothing'], '--format' => 'agent'], ['interactive' => false]);

        $document = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
        expect($document['error'])->toContain('o_nothing');
    });
});
