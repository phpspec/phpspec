<?php

use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\Response;
use PhpSpec\Ai\ToolCall;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Refactor;
use PhpSpec\Console\Command\Refactor\SpecSuite;
use PhpSpec\Console\Command\Refactor\SuiteCheck;
use PhpSpec\Console\Command\Run\Generation;
use PhpSpec\Console\Consent;
use PhpSpec\Console\Prompt;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/../../Ai/ReplayProvider.php';
require_once __DIR__ . '/Refactor/RefactorWorld.php';

describe(Refactor::class, function () {

    let('cwd', fn() => getcwd());
    let('project', function () {
        $fs = new RefactorWorldFilesystem();
        $fs->write($this->cwd . '/src/App/Checkout/CheckoutService.php', "<?php\nnamespace App\\Checkout;\n\nfinal class CheckoutService {}\n");
        $fs->write($this->cwd . '/spec/App/Checkout/CheckoutService.spec.php', "<?php\ndescribe(App\\Checkout\\CheckoutService::class, function () {});\n");

        return $fs;
    });
    let('green', fn() => SuiteCheck::fromStream('{"event":"summary","actionable":0}'));
    let('redIn', fn() => fn(string $spec, string $said): SuiteCheck => SuiteCheck::fromStream(
        json_encode(['event' => 'example', 'state' => 'failing', 'spec' => $spec, 'message' => $said]) . "\n" . '{"event":"summary","actionable":1}',
    ));
    let('plan', fn() => fn(string ...$titles): Response => new Response('', [new ToolCall('p', 'propose_plan', [
        'technique' => 'Replace Conditional with Polymorphism',
        'rationale' => "CheckoutService decides every discount itself.\n\nA DiscountPolicy takes each rule:\n  - loyalty customers\n  - seasonal campaigns",
        'steps' => array_map(fn(string $title): array => ['title' => $title, 'doing' => 'Doing ' . $title], $titles),
    ])], 1200));
    let('step', fn() => fn(array $files, bool $red = false): Response => new Response('', [new ToolCall('s', 'propose_step', [
        'files' => array_map(fn(string $path, string $content): array => ['path' => $path, 'content' => $content], array_keys($files), $files),
        'red' => $red,
    ])], 900));
    let('run', fn() => function (array $responses, array $checks, array $answers = [], bool $interactive = true, string $target = 'App\\Checkout\\CheckoutService'): CommandTester {
        $this->replay = new ReplayProvider($responses);
        $suite = new class ($checks) implements SpecSuite {
            public function __construct(private array $checks) {}

            public function check(): SuiteCheck
            {
                return array_shift($this->checks);
            }
        };
        $prompt = new class ($answers) extends Prompt {
            public function __construct(private array $answers) {}

            public function ask(string $prompt): ?string
            {
                return array_shift($this->answers);
            }
        };
        $consent = new Consent('Run phpspec refactor in a terminal to %s.', $interactive ? Generation::Asks : Generation::Declines, prompt: $prompt);
        $command = new Refactor(new Configuration(['ai' => ['provider' => 'google', 'api_key' => 'test-key']]), $this->project, $suite, $this->replay, $consent);
        $app = new Application('phpspec-test', '1.0');
        $app->setAutoExit(false);
        $app->{method_exists($app, 'addCommand') ? 'addCommand' : 'add'}($command);

        $tester = new CommandTester($command);
        $tester->execute($target === '' ? [] : ['target' => $target]);

        return $tester;
    });

    context('a plan', function () {

        it('shows the technique, why, and the plan, and writes nothing when told no', function () {
            $before = $this->project->files;

            $tester = ($this->run)([($this->plan)('Introduce a DiscountPolicy abstraction', 'Describe LoyaltyDiscount')], [$this->green], ['n']);
            $display = $tester->getDisplay();

            expect($tester->getStatusCode())->toBe(0);
            expect($display)->toContain('  Checking the specs...');
            expect($display)->toMatch('/Planning the refactoring… \(\d+s · ↓ 1\.2k tokens\)/');
            expect($display)->toContain("Replace Conditional with Polymorphism\n\nCheckoutService decides every discount itself.\n\nA DiscountPolicy takes each rule:\n  - loyalty customers\n  - seasonal campaigns\n\nPlan:\n\n  ◻ Introduce a DiscountPolicy abstraction\n  ◻ Describe LoyaltyDiscount\n\nPhpSpec has written a refactoring plan and is ready to start.\n");
            expect($display)->toContain('Would you like to proceed? [Y/n]');
            expect($display)->toContain('Left unchanged.');
            expect($this->project->files)->toBe($before);
            expect($this->replay->requests)->toHaveCount(1);
        });

        it('prints the plan and writes nothing with nobody to answer', function () {
            $tester = ($this->run)([($this->plan)('Introduce a DiscountPolicy abstraction')], [$this->green], interactive: false);

            expect($tester->getStatusCode())->toBe(0);
            expect($tester->getDisplay())->toContain('Nothing was written: there is nobody to answer. Run phpspec refactor in a terminal to carry out the plan.');
            expect($this->replay->requests)->toHaveCount(1);
        });

        it('says there is nothing to refactor in the class, in the model\'s words, when it declines', function () {
            $tester = ($this->run)([new Response('', [new ToolCall('d', 'decline_refactoring', ['reason' => 'It does one thing.'])])], [$this->green]);

            expect($tester->getStatusCode())->toBe(0);
            expect($tester->getDisplay())->toContain('  Nothing to refactor in App\\Checkout\\CheckoutService: It does one thing.');
        });
    });

    context('the steps', function () {

        it('carries out the plan one step at a time, asking at each and ticking each done', function () {
            $tester = ($this->run)([
                ($this->plan)('Introduce a DiscountPolicy abstraction', 'Use it in CheckoutService'),
                ($this->step)(['src/App/Checkout/DiscountPolicy.php' => "<?php\nnamespace App\\Checkout;\n\ninterface DiscountPolicy {}\n"]),
                ($this->step)(['src/App/Checkout/CheckoutService.php' => "<?php\nnamespace App\\Checkout;\n\nfinal class CheckoutService { /* policy */ }\n"]),
            ], [$this->green, $this->green, $this->green], ['', '', '']);
            $display = $tester->getDisplay();

            expect($tester->getStatusCode())->toBe(0);
            expect($display)->toMatch('/Doing Introduce a DiscountPolicy abstraction… \(\d+s · ↓ 900 tokens\)\n  ◼ Introduce a DiscountPolicy abstraction\n  ◻ Use it in CheckoutService\n/');
            expect($display)->toContain("  ✔ Introduce a DiscountPolicy abstraction\n  ◼ Use it in CheckoutService\n");
            expect($display)->toContain('  src/App/Checkout/DiscountPolicy.php (new)');
            expect(substr_count($display, 'Apply this step? [Y/n]'))->toBe(2);
            expect(substr_count($display, '  Specs still pass ✓'))->toBe(2);
            expect($display)->toContain('  Refactoring done: Replace Conditional with Polymorphism ✓');
            expect($this->project->files[$this->cwd . '/src/App/Checkout/DiscountPolicy.php'])->toContain('interface DiscountPolicy');
            expect($this->project->files[$this->cwd . '/src/App/Checkout/CheckoutService.php'])->toContain('/* policy */');
            expect($this->project->files[$this->cwd . '/.phpspec/ai/journal.jsonl'] ?? '')->toContain('Replace Conditional with Polymorphism');
        });

        it('lets a step meant to be red stay red in the spec it writes, until a later step makes it green', function () {
            $tester = ($this->run)([
                ($this->plan)('Describe LoyaltyDiscount', 'Write LoyaltyDiscount'),
                ($this->step)(['spec/App/Checkout/LoyaltyDiscount.spec.php' => "<?php // red first\n"], red: true),
                ($this->step)(['src/App/Checkout/LoyaltyDiscount.php' => "<?php // green\n"]),
            ], [$this->green, ($this->redIn)('spec/App/Checkout/LoyaltyDiscount.spec.php:3', 'Class "App\\Checkout\\LoyaltyDiscount" not found'), $this->green], ['', '', '']);

            expect($tester->getStatusCode())->toBe(0);
            expect($tester->getDisplay())->toContain('  Red, as this step meant: spec/App/Checkout/LoyaltyDiscount.spec.php');
            expect($tester->getDisplay())->toContain('Refactoring done');
        });

        it('asks for one fix when a step breaks a spec it did not mean to, and keeps the fix when it holds', function () {
            $tester = ($this->run)([
                ($this->plan)('Use a DiscountPolicy in CheckoutService'),
                ($this->step)(['src/App/Checkout/CheckoutService.php' => "<?php // first try\n"]),
                ($this->step)(['src/App/Checkout/CheckoutService.php' => "<?php // fixed\n"]),
            ], [$this->green, ($this->redIn)('spec/App/Checkout/CheckoutService.spec.php:7', 'Expected 90 to be 100'), $this->green], ['', '', '']);
            $display = $tester->getDisplay();

            expect($tester->getStatusCode())->toBe(0);
            expect($display)->toContain('  Specs failed ✘, asking for a fix...');
            expect($display)->toContain('spec/App/Checkout/CheckoutService.spec.php:7  Expected 90 to be 100');
            expect($display)->toContain('Apply this fix? [Y/n]');
            expect($this->project->files[$this->cwd . '/src/App/Checkout/CheckoutService.php'])->toBe("<?php // fixed\n");
            expect(end($this->replay->requests[2]['messages'])->content)->toContain('Expected 90 to be 100');
        });

        it('undoes the step and stops when its fix does not hold, keeping the steps before it', function () {
            $original = $this->project->files[$this->cwd . '/src/App/Checkout/CheckoutService.php'];
            $broken = ($this->redIn)('spec/App/Checkout/CheckoutService.spec.php:7', 'Expected 90 to be 100');

            $tester = ($this->run)([
                ($this->plan)('Introduce a DiscountPolicy abstraction', 'Use it in CheckoutService'),
                ($this->step)(['src/App/Checkout/DiscountPolicy.php' => "<?php // kept\n"]),
                ($this->step)(['src/App/Checkout/CheckoutService.php' => "<?php // broken\n", 'src/App/Checkout/LoyaltyDiscount.php' => "<?php // new\n"]),
                ($this->step)(['src/App/Checkout/CheckoutService.php' => "<?php // still broken\n"]),
            ], [$this->green, $this->green, $broken, $broken], ['', '', '', '']);

            expect($tester->getStatusCode())->toBe(1);
            expect($tester->getDisplay())->toContain('Stopped at step 2: the steps before it are kept, this one is undone.');
            expect($this->project->files[$this->cwd . '/src/App/Checkout/DiscountPolicy.php'])->toBe("<?php // kept\n");
            expect($this->project->files[$this->cwd . '/src/App/Checkout/CheckoutService.php'])->toBe($original);
            expect($this->project->files)->not()->toHaveKey($this->cwd . '/src/App/Checkout/LoyaltyDiscount.php');
        });

        it('stops before a step the developer turns down, keeping the steps before it', function () {
            $tester = ($this->run)([
                ($this->plan)('Introduce a DiscountPolicy abstraction', 'Use it in CheckoutService'),
                ($this->step)(['src/App/Checkout/DiscountPolicy.php' => "<?php // kept\n"]),
                ($this->step)(['src/App/Checkout/CheckoutService.php' => "<?php // not wanted\n"]),
            ], [$this->green, $this->green], ['', '', 'n']);

            expect($tester->getStatusCode())->toBe(0);
            expect($tester->getDisplay())->toContain('Stopped before step 2: the steps before it are kept.');
            expect($this->project->files)->toHaveKey($this->cwd . '/src/App/Checkout/DiscountPolicy.php');
            expect($this->project->files[$this->cwd . '/src/App/Checkout/CheckoutService.php'])->not()->toContain('not wanted');
        });

        it('stops on a step that writes outside the source and spec paths, writing nothing of it', function () {
            $before = $this->project->files;

            $tester = ($this->run)([
                ($this->plan)('Introduce a DiscountPolicy abstraction'),
                ($this->step)(['vendor/acme/Policy.php' => "<?php\n"]),
            ], [$this->green], ['']);

            expect($tester->getStatusCode())->toBe(1);
            expect($tester->getDisplay())->toContain('The step writes vendor/acme/Policy.php, which is not a PHP file under src or spec: nothing of it was written.');
            expect($this->project->files)->toBe($before);
        });
    });

    context('before a plan', function () {

        it('refuses to start when the specs are not green, saying what failed', function () {
            $tester = ($this->run)([], [($this->redIn)('spec/App/Checkout/CheckoutService.spec.php:7', 'Expected 90 to be 100')]);

            expect($tester->getStatusCode())->toBe(1);
            expect($tester->getDisplay())->toContain('Specs must pass before refactoring.');
            expect($tester->getDisplay())->toContain('spec/App/Checkout/CheckoutService.spec.php:7  Expected 90 to be 100');
            expect($this->replay->requests)->toBe([]);
        });

        it('refuses a class with no spec, saying how to give it one', function () {
            $this->project->delete($this->cwd . '/spec/App/Checkout/CheckoutService.spec.php');

            $tester = ($this->run)([], []);

            expect($tester->getStatusCode())->toBe(1);
            expect($tester->getDisplay())->toContain('App\\Checkout\\CheckoutService has no spec, so nothing would catch a refactoring that broke it. Describe it first: phpspec describe App\\Checkout\\CheckoutService');
        });

        it('takes the source modified last when given no target, without announcing it', function () {
            $tester = ($this->run)([new Response('', [new ToolCall('d', 'decline_refactoring', ['reason' => 'Clean.'])])], [$this->green], target: '');

            expect($tester->getDisplay())->toContain('Nothing to refactor in App\\Checkout\\CheckoutService: Clean.');
            expect($tester->getDisplay())->not()->toContain('Refactoring App');
        });

        it('asks for AI configuration when there is none', function () {
            $tester = new CommandTester(new Refactor(new Configuration(), $this->project));
            $tester->execute(['target' => 'App\\Checkout\\CheckoutService']);

            expect($tester->getStatusCode())->toBe(1);
            expect($tester->getDisplay())->toContain('AI configuration required');
        });

        it('stops on a model it cannot reach, before checking the specs', function () {
            $checked = false;
            $suite = new class ($checked) implements SpecSuite {
                public function __construct(private bool &$checked) {}

                public function check(): SuiteCheck
                {
                    $this->checked = true;

                    return SuiteCheck::fromStream('{"event":"summary","actionable":0}');
                }
            };
            $tester = new CommandTester(new Refactor(new Configuration(['ai' => ['provider' => 'anthropic', 'api_key' => 'k']]), $this->project, $suite));
            $tester->execute(['target' => 'App\\Checkout\\CheckoutService']);

            expect($tester->getStatusCode())->toBe(1);
            expect($tester->getDisplay())->toContain('composer require papi-ai/anthropic');
            expect($checked)->toBeFalse();
        });
    });
});
