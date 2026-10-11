<?php

use PhpSpec\Ai\Response;
use PhpSpec\Ai\ToolCall;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Accept;
use PhpSpec\Console\Command\Refactor\PlannedStep;
use PhpSpec\Console\Command\Refactor\PlanOffers;
use PhpSpec\Console\Command\Refactor\RefactorPlan;
use PhpSpec\Console\Command\Refactor\SpecSuite;
use PhpSpec\Console\Command\Refactor\SuiteCheck;
use PhpSpec\Filesystem;
use PhpSpec\Offers\Offer;
use PhpSpec\Offers\OfferBook;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/../../Ai/ReplayProvider.php';
require_once __DIR__ . '/Refactor/RefactorWorld.php';

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

    it('refuses an offer that expired, saying when it was made and how long one stays', function (Filesystem $fs) {
        $made = 1_700_000_000;
        $offer = Offer::generate('create_class', 'App\\Coupon', []);
        (new OfferBook($fs, '/project', static fn(): int => $made))->record($offer);

        $tester = new CommandTester(new Accept($fs, new OfferBook($fs, '/project', static fn(): int => $made + 13 * 86_400), '/project'));
        $tester->execute(['offer' => [$offer->id]], ['interactive' => false]);

        expect($tester->getStatusCode())->toBe(1);
        expect($tester->getDisplay())->toContain('Offer "' . $offer->id . '" has expired: it was made 13 days ago and an offer stays for 20 minutes.');
        expect($this->written)->toBe([]);
    });

    it('tells an agent an offer expired, as an error on its own channel', function (Filesystem $fs) {
        $made = 1_700_000_000;
        $offer = Offer::generate('create_class', 'App\\Coupon', []);
        (new OfferBook($fs, '/project', static fn(): int => $made))->record($offer);

        $tester = new CommandTester(new Accept($fs, new OfferBook($fs, '/project', static fn(): int => $made + 25 * 60), '/project'));
        $tester->execute(['offer' => [$offer->id], '--format' => 'agent'], ['interactive' => false]);

        $document = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
        expect($document['accepted'])->toBe([]);
        expect($document['error'])->toContain('has expired: it was made 25 minutes ago and an offer stays for 20 minutes.');
        expect($tester->getStatusCode())->toBe(1);
    });

    it('refuses an offer the code has moved past', function () {
        $offer = Offer::write('src/App/Basket.php', '<?php the proposal', false, 'the content when offered');
        $this->book->record($offer);
        $this->files['/project/src/App/Basket.php'] = 'someone edited it since';

        $tester = ($this->tester)();
        $tester->execute(['offer' => [$offer->id]], ['interactive' => false]);

        expect($tester->getStatusCode())->toBe(1);
        expect($tester->getDisplay())->toContain('changed since the offer was made. Run the command that made it again to get a fresh one.');
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
        expect($document['accepted'][0])->toBe([
            'id' => $offer->id,
            'action' => 'create',
            'target' => 'src/App/Basket.php',
            'files' => ['src/App/Basket.php'],
            'applied' => true,
        ]);
    });

    it('reports an offer it could not apply as not applied, with the reason, and exits 1', function () {
        $offer = Offer::generate('create_method', 'App\\Calc::add', [
            'undefinedClassMethods' => [['className' => 'App\\Calc', 'methodName' => 'add', 'file' => '/project/spec/App/Calc.spec.php', 'line' => 3]],
        ]);
        $this->book->record($offer);

        $tester = ($this->tester)();
        $tester->execute(['offer' => [$offer->id], '--format' => 'agent'], ['interactive' => false]);

        $document = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
        expect($tester->getStatusCode())->toBe(1);
        expect($document['accepted'][0]['applied'])->toBeFalse();
        expect($document['accepted'][0]['reason'])->toContain('Source file not found');
        expect($document['accepted'][0]['files'])->toBe([]);
        expect($this->written)->toBe([]);
    });

    it('tells a person why an offer was not applied', function () {
        $offer = Offer::generate('create_method', 'App\\Calc::add', [
            'undefinedClassMethods' => [['className' => 'App\\Calc', 'methodName' => 'add', 'file' => '/project/spec/App/Calc.spec.php', 'line' => 3]],
        ]);
        $this->book->record($offer);

        $tester = ($this->tester)();
        $tester->execute(['offer' => [$offer->id]], ['interactive' => false]);

        expect($tester->getStatusCode())->toBe(1);
        expect($tester->getDisplay())->toContain(sprintf('Offer %s was not applied: Source file not found', $offer->id));
    });

    it('refuses an offer it already took, saying so', function () {
        $offer = Offer::write('src/App/Basket.php', '<?php', true, '');
        $this->book->record($offer);
        ($this->tester)()->execute(['offer' => [$offer->id]], ['interactive' => false]);

        $again = ($this->tester)();
        $again->execute(['offer' => [$offer->id]], ['interactive' => false]);

        expect($again->getStatusCode())->toBe(1);
        expect($again->getDisplay())->toContain(sprintf('Offer "%s" was already accepted.', $offer->id));
    });

    it('asks for an offer when given none, on the channel its reader reads', function () {
        $tester = ($this->tester)();
        $tester->execute(['--format' => 'agent'], ['interactive' => false]);

        expect($tester->getStatusCode())->toBe(1);
        expect(json_decode(trim($tester->getDisplay()), true)['error'] ?? null)->toBe('Name the offer to accept: phpspec accept <id>.');
    });

    it('tells an agent why it refused, on the same channel', function () {
        $tester = ($this->tester)();
        $tester->execute(['offer' => ['o_nothing'], '--format' => 'agent'], ['interactive' => false]);

        $document = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
        expect($document['error'])->toContain('o_nothing');
    });

    context('a refactoring plan', function () {

        beforeEach(function () {
            $this->world = new RefactorWorldFilesystem();
            $this->world->write('/project/src/App/Till.php', "<?php\nnamespace App;\n\nfinal class Till {}\n");
            $this->world->write('/project/spec/App/Till.spec.php', "<?php\ndescribe(App\\Till::class, function () {});\n");
            $plan = new RefactorPlan('Extract Class', 'Till prints its own receipt.', [new PlannedStep('Introduce a Printer', 'Introducing Printer')]);
            $this->offer = Offer::refactor('App\\Till', $plan->toArray(), [
                'src/App/Till.php' => sha1($this->world->files['/project/src/App/Till.php']),
                'spec/App/Till.spec.php' => sha1($this->world->files['/project/spec/App/Till.spec.php']),
            ]);
            $this->planBook = new OfferBook($this->world, '/project');
            $this->planBook->record($this->offer);
            $this->green = SuiteCheck::fromStream('{"event":"summary","examples":1,"actionable":0}');
            $this->red = SuiteCheck::fromStream(json_encode(['event' => 'example', 'state' => 'failing', 'spec' => 'spec/App/Till.spec.php:3', 'message' => 'Expected 1 to be 2']) . "\n" . '{"event":"summary","examples":1,"actionable":1}');
            $this->step = fn(string $path, string $content): Response => new Response('', [new ToolCall('s', 'propose_step', ['files' => [['path' => $path, 'content' => $content]], 'red' => false])]);
            $this->take = function (array $responses, array $checks): CommandTester {
                $suite = new class ($checks) implements SpecSuite {
                    public function __construct(private array $checks) {}

                    public function check(): SuiteCheck
                    {
                        return array_shift($this->checks);
                    }
                };
                $config = new Configuration(['ai' => ['provider' => 'google', 'api_key' => 'k']]);
                $tester = new CommandTester(new Accept($this->world, $this->planBook, '/project', $config, new PlanOffers($config, $this->world, '/project', $suite, new ReplayProvider($responses))));
                $tester->execute(['offer' => [$this->offer->id], '--format' => 'agent'], ['interactive' => false]);

                return $tester;
            };
            $this->accepted = fn(CommandTester $tester): array => json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR)['accepted'][0] ?? [];
        });

        it('carries the plan out with nobody asked, naming the files its steps wrote', function () {
            $tester = ($this->take)([($this->step)('src/App/Printer.php', "<?php\nnamespace App;\n\nfinal class Printer {}\n")], [$this->green, $this->green]);

            expect($tester->getStatusCode())->toBe(0);
            expect(($this->accepted)($tester))->toBe([
                'id' => $this->offer->id,
                'action' => 'refactor',
                'target' => 'App\\Till',
                'files' => ['src/App/Printer.php'],
                'applied' => true,
            ]);
            expect($this->world->files['/project/src/App/Printer.php'] ?? '')->toContain('final class Printer');
        });

        it('reports a plan that stopped short as not applied, saying where it stopped', function () {
            $tester = ($this->take)([
                ($this->step)('src/App/Till.php', "<?php // broken\n"),
                ($this->step)('src/App/Till.php', "<?php // still broken\n"),
            ], [$this->green, $this->red, $this->red]);

            expect($tester->getStatusCode())->toBe(1);
            expect(($this->accepted)($tester))->toBe([
                'id' => $this->offer->id,
                'action' => 'refactor',
                'target' => 'App\\Till',
                'files' => [],
                'applied' => false,
                'reason' => "Stopped at step 1: step 1 is undone.\nspec/App/Till.spec.php:3  Expected 1 to be 2",
            ]);
            expect($this->world->files['/project/src/App/Till.php'])->toContain('final class Till {}');
        });

        it('says in the reason what the model said when it did not answer', function () {
            $tester = ($this->take)([new RuntimeException('Google API error (503): The service is currently unavailable.')], [$this->green]);

            expect(($this->accepted)($tester)['reason'])->toBe("Stopped at step 1: nothing was written.\nThe model did not answer: Google API error (503): The service is currently unavailable.");
        });

        it('refuses to start over specs that do not pass', function () {
            $tester = ($this->take)([], [$this->red]);

            expect($tester->getStatusCode())->toBe(1);
            expect(($this->accepted)($tester)['applied'])->toBeFalse();
            expect(($this->accepted)($tester)['reason'])->toBe("Specs must pass before refactoring. Fix what the run left first.\nspec/App/Till.spec.php:3  Expected 1 to be 2");
        });

        it('refuses a plan made reading a file that has changed since', function () {
            $this->world->write('/project/spec/App/Till.spec.php', "<?php // edited\n");

            $tester = ($this->take)([], []);

            expect($tester->getStatusCode())->toBe(1);
            expect(json_decode(trim($tester->getDisplay()), true)['error'])->toBe(sprintf('Offer "%s" no longer fits spec/App/Till.spec.php, which changed since the offer was made. Run the command that made it again to get a fresh one.', $this->offer->id));
        });
    });
});
