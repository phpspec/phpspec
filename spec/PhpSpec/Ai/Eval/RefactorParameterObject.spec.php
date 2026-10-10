<?php

use PhpSpec\Configuration;
use PhpSpec\Console\Command\Refactor;
use PhpSpec\Console\Command\Refactor\SpecSuite;
use PhpSpec\Console\Command\Refactor\SuiteCheck;
use PhpSpec\Console\Command\Run\Generation;
use PhpSpec\Console\Consent;
use PhpSpec\Console\Prompt;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/../ReplayProvider.php';
require_once __DIR__ . '/../../Console/Command/Refactor/RefactorWorld.php';

// E14 (eval): a live refactoring of a pricer whose three primitives travel
// together, replayed through the command. The suite is read off the files the
// replayed steps write: every it() an example, a spec whose class does not
// exist yet in error. So what is graded is what the model wrote: the new
// collaborator described before it exists, nothing written outside src and
// spec, no example lost on the way, the subject left delegating.
describe('E14 refactor: a parameter object, spec first, replayed from a live session', function () {

    beforeEach(function () {
        $this->root = getcwd();
        $this->world = new RefactorWorldFilesystem();
        $this->world->write($this->root . '/src/Brew/Money.php', <<<'PHP'
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

            PHP);
        $this->world->write($this->root . '/src/Brew/DrinkPricer.php', <<<'PHP'
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

            PHP);
        $this->world->write($this->root . '/spec/Brew/DrinkPricer.spec.php', <<<'PHP'
            <?php

            use Brew\DrinkPricer;
            use Brew\Money;

            describe(DrinkPricer::class, function () {
                let('pricer', fn () => new DrinkPricer());

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

            PHP);
        $this->recording = json_decode((string) file_get_contents(__DIR__ . '/recordings/refactor-parameter-object.json'), true);
        $this->examples = [];
        $this->suite = new class ($this->world, $this->root, $this->examples) implements SpecSuite {
            public function __construct(private RefactorWorldFilesystem $world, private string $root, private array &$examples) {}

            public function check(): SuiteCheck
            {
                $events = [];
                $examples = 0;

                foreach ($this->world->files as $path => $content) {
                    if (!str_ends_with($path, '.spec.php')) {
                        continue;
                    }

                    $examples += preg_match_all('/\bit\(/', $content);
                    $spec = substr($path, strlen($this->root) + 1);
                    $source = $this->root . '/src/' . substr($spec, strlen('spec/'), -strlen('.spec.php')) . '.php';

                    if (!isset($this->world->files[$source])) {
                        $events[] = json_encode(['event' => 'example', 'state' => 'error', 'spec' => $spec . ':5', 'message' => 'Class not found']);
                    }
                }

                $this->examples[] = $examples;
                $events[] = json_encode(['event' => 'summary', 'examples' => $examples, 'actionable' => count($events)]);

                return SuiteCheck::fromStream(implode("\n", $events));
            }
        };
        $this->refactor = function (): CommandTester {
            $this->replay = ReplayProvider::fromConversation($this->recording);
            $yes = new class extends Prompt {
                public function ask(string $prompt): ?string
                {
                    return 'y';
                }
            };
            $command = new Refactor(new Configuration(['ai' => ['provider' => 'google', 'api_key' => 'k']]), $this->world, $this->suite, $this->replay, new Consent('%s', Generation::Asks, prompt: $yes));
            $tester = new CommandTester($command);
            $tester->execute(['target' => 'Brew\\DrinkPricer']);

            return $tester;
        };
        $this->code = fn(string $path): string => $this->world->files[$this->root . '/' . $path] ?? '';
    });

    it('carries the plan out to the end, every step standing', function () {
        $tester = ($this->refactor)();

        expect($tester->getStatusCode())->toBe(0);
        expect($tester->getDisplay())->toContain('Refactoring done: Introduce Parameter Object ✓');
        expect(substr_count($tester->getDisplay(), 'Specs still pass ✓'))->toBe(4);
    });

    it('describes the new collaborator before it exists, red only in its own spec', function () {
        $tester = ($this->refactor)();

        expect($tester->getDisplay())->toContain('Red, as this step meant: spec/Brew/Drink.spec.php');
        $firstStep = $this->recording['turns'][0]['rounds'][1]['response']['tool_calls'][0]['arguments'];
        expect(array_column($firstStep['files'], 'path'))->toBe(['spec/Brew/Drink.spec.php']);
        expect($firstStep['red'])->toBeTrue();
    });

    it('writes nothing outside the source and spec paths, and loses no example on the way', function () {
        ($this->refactor)();

        $written = array_map(fn(string $path): string => substr($path, strlen($this->root) + 1), array_keys($this->world->files));
        expect(array_values(array_filter($written, fn(string $path): bool => !str_starts_with($path, 'src/') && !str_starts_with($path, 'spec/') && !str_starts_with($path, '.phpspec/'))))->toBe([]);
        expect($this->examples)->toBe([4, 5, 5, 5, 6, 6]);
    });

    it('leaves the subject delegating to the parameter object, its old signature gone', function () {
        ($this->refactor)();

        expect(($this->code)('src/Brew/DrinkPricer.php'))->not()->toContain('function priceOf(');
        expect(($this->code)('src/Brew/DrinkPricer.php'))->toContain('return $drink->price();');
        expect(($this->code)('src/Brew/Drink.php'))->toContain('public function price(): Money');
    });

    it('asks for the plan and for each step through a forced tool call', function () {
        ($this->refactor)();

        $choices = array_map(fn(array $request): mixed => $request['options']['toolChoice'], $this->replay->requests);
        expect($choices)->toBe(['required', ...array_fill(0, 5, ['name' => 'propose_step'])]);
    });
});
