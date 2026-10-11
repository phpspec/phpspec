<?php

use PhpSpec\Configuration;
use PhpSpec\Console\Command\Refactor;
use PhpSpec\Console\Command\Refactor\SpecSuite;
use PhpSpec\Console\Command\Refactor\SuiteCheck;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/../ReplayProvider.php';
require_once __DIR__ . '/../../Console/Command/Refactor/RefactorWorld.php';

// E15 (eval): a live run on a till that already delegates its pricing and its
// discount, replayed through the command. Declining is a good answer: it
// reaches the developer in the model's words, and a coding agent as its
// receipt, and nothing is written.
describe('E15 refactor: a clean class is declined, replayed from a live session', function () {

    beforeEach(function () {
        $this->root = getcwd();
        $this->world = new RefactorWorldFilesystem();
        $this->world->write($this->root . '/src/Brew/Till.php', <<<'PHP'
            <?php

            namespace Brew;

            final class Till
            {
                private Money $subtotal;
                private Receipt $receipt;

                public function __construct(
                    private readonly Menu $menu,
                    private readonly Discount $discount,
                ) {
                    $this->subtotal = new Money(0);
                    $this->receipt = new Receipt();
                }

                public function order(int $quantity, string $item): void
                {
                    $linePrice = $this->menu->priceOf($item)->times($quantity);

                    $this->subtotal = $this->subtotal->plus($linePrice);
                    $this->receipt->add($quantity, $item, $linePrice);
                }

                public function total(): Money
                {
                    return $this->discount->applyTo($this->subtotal);
                }

                /**
                 * @return list<string>
                 */
                public function receiptLines(): array
                {
                    return [
                        ...$this->receipt->lines(),
                        sprintf('Total  %s', $this->total()->format()),
                    ];
                }
            }
            PHP);
        $this->world->write($this->root . '/spec/Brew/Till.spec.php', <<<'PHP'
            <?php

            use Brew\Discount;
            use Brew\Menu;
            use Brew\Money;
            use Brew\NotOnTheMenuException;
            use Brew\Till;

            describe(Till::class, function () {
                let('menu', fn () => mock(Menu::class));
                let('discount', fn () => mock(Discount::class));
                let('till', fn () => new Till($this->menu, $this->discount));

                beforeEach(function () {
                    allow($this->menu->priceOf('espresso'))->toReturn(new Money(220));
                    allow($this->menu->priceOf('flat white'))->toReturn(new Money(340));
                });

                it('charges what the discount leaves of the order', function () {
                    allow($this->discount->applyTo(callback(fn (Money $subtotal) => $subtotal == new Money(900))))->toReturn(new Money(720));

                    $this->till->order(2, 'flat white');
                    $this->till->order(1, 'espresso');

                    expect($this->till->total())->toBeLike(new Money(720));
                });

                it('prices each order line once', function () {
                    expect($this->menu->priceOf('mocha'))->toBeCalledTimes(1)->toReturn(new Money(300));

                    $this->till->order(3, 'mocha');
                });

                it('writes each order line and the total on the receipt', function () {
                    allow($this->discount->applyTo(new Money(680)))->toReturn(new Money(680));

                    $this->till->order(2, 'flat white');

                    expect($this->till->receiptLines())->toBe([
                        '2 x flat white  £6.80',
                        'Total  £6.80'
                    ]);
                });

                it('lets a refusal from the menu through', function () {
                    allow($this->menu->priceOf('unicorn latte'))->toThrow(NotOnTheMenuException::for('unicorn latte'));

                    expect(fn () => $this->till->order(1, 'unicorn latte'))->toThrow(NotOnTheMenuException::class);
                });
            });
            PHP);
        $this->before = $this->world->files;
        $this->recording = json_decode((string) file_get_contents(__DIR__ . '/recordings/refactor-decline-clean-class.json'), true);
        $this->reason = $this->recording['turns'][0]['rounds'][0]['response']['tool_calls'][0]['arguments']['reason'];
        $this->refactor = function (array $options = []): CommandTester {
            $this->replay = ReplayProvider::fromConversation($this->recording);
            $green = new class implements SpecSuite {
                public function check(): SuiteCheck
                {
                    return SuiteCheck::fromStream('{"event":"summary","examples":4,"actionable":0}');
                }
            };
            $tester = new CommandTester(new Refactor(new Configuration(['ai' => ['provider' => 'google', 'api_key' => 'k']]), $this->world, $green, $this->replay));
            $tester->execute(['target' => 'Till'] + $options);

            return $tester;
        };
        $this->code = fn(): array => array_filter($this->world->files, fn(string $path): bool => !str_contains($path, '/.phpspec/'), ARRAY_FILTER_USE_KEY);
    });

    it('says there is nothing to refactor, in the model\'s words, and writes nothing', function () {
        $tester = ($this->refactor)();

        expect($tester->getStatusCode())->toBe(0);
        expect($tester->getDisplay())->toContain('Nothing to refactor in Brew\\Till: ' . $this->reason);
        expect(($this->code)())->toBe($this->before);
    });

    it('answers a coding agent with the reason as its receipt', function () {
        $tester = ($this->refactor)(['--format' => 'agent']);

        expect(json_decode($tester->getDisplay(), true)['declined'])->toBe($this->reason);
        expect(($this->code)())->toBe($this->before);
    });

    it('offered the model a plan or a decline, and made it answer with one of them', function () {
        ($this->refactor)();

        $request = $this->replay->requests[0];
        expect(array_map(fn($tool) => $tool->getName(), $request['options']['tools']))->toBe(['propose_plan', 'decline_refactoring']);
        expect($request['options']['toolChoice'])->toBe('required');
    });
});
