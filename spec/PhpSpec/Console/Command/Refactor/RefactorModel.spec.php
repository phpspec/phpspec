<?php

use PhpSpec\Ai\PromptLibrary;
use PhpSpec\Ai\Response;
use PhpSpec\Ai\ToolCall;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Refactor\Declined;
use PhpSpec\Console\Command\Refactor\PlannedStep;
use PhpSpec\Console\Command\Refactor\RefactorModel;
use PhpSpec\Console\Command\Refactor\RefactorPlan;
use PhpSpec\Console\Command\Refactor\RefactorTarget;
use PhpSpec\Console\Command\Refactor\RefusedStepException;
use PhpSpec\Filesystem;

require_once __DIR__ . '/../../../Ai/ReplayProvider.php';

describe(RefactorModel::class, function () {

    let('files', fn() => new ArrayObject([
        '/proj/src/App/Checkout/CheckoutService.php' => "<?php\nnamespace App\\Checkout;\n\nuse App\\Pricing\\Rates;\n\nfinal class CheckoutService {}\n",
        '/proj/spec/App/Checkout/CheckoutService.spec.php' => "<?php\ndescribe('App\\Checkout\\CheckoutService', function () {});\n",
        '/proj/src/App/Pricing/Rates.php' => "<?php\nnamespace App\\Pricing;\n\nfinal class Rates {}\n",
    ]));
    let('filesystem', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path): bool => isset($this->files[$path]));
        allow($fs->read())->toReturnUsing(fn(string $path): string => $this->files[$path] ?? '');

        return $fs;
    });
    let('target', fn() => new RefactorTarget('App\\Checkout\\CheckoutService', '/proj/src/App/Checkout/CheckoutService.php', '/proj/spec/App/Checkout/CheckoutService.spec.php'));
    let('plan', fn() => new Response('', [new ToolCall('p1', 'propose_plan', [
        'technique' => 'Replace Conditional with Polymorphism',
        'rationale' => "CheckoutService decides every discount itself.\n\nA DiscountPolicy takes each rule.",
        'steps' => [
            ['title' => 'Introduce a DiscountPolicy abstraction', 'doing' => 'Introducing DiscountPolicy'],
            'Describe LoyaltyDiscount',
        ],
    ])], 1200));
    let('replay', fn() => new ReplayProvider([]));
    let('model', fn() => function (Response ...$responses): RefactorModel {
        $this->replay = new ReplayProvider($responses);

        return new RefactorModel($this->replay, new Configuration(['ai' => ['provider' => 'google', 'api_key' => 'k']]), $this->filesystem, '/proj', new PromptLibrary($this->filesystem));
    });

    it('asks for a plan with the subject, its spec and the classes it imports, and reads it', function () {
        $plan = ($this->model)($this->plan)->plan($this->target, '');

        expect($plan)->toBeAnInstanceOf(RefactorPlan::class);
        expect($plan->technique)->toBe('Replace Conditional with Polymorphism');
        expect($plan->rationale)->toBe("CheckoutService decides every discount itself.\n\nA DiscountPolicy takes each rule.");
        expect($plan->steps)->toBeLike([new PlannedStep('Introduce a DiscountPolicy abstraction', 'Introducing DiscountPolicy'), new PlannedStep('Describe LoyaltyDiscount', 'Describe LoyaltyDiscount')]);
        expect($plan->tokens)->toBe(1200);

        $request = $this->replay->requests[0];
        $asked = $request['messages'][1]->content;
        expect($asked)->toContain("# src/App/Checkout/CheckoutService.php\n");
        expect($asked)->toContain("# spec/App/Checkout/CheckoutService.spec.php\n");
        expect($asked)->toContain("# src/App/Pricing/Rates.php\n");
        expect($request['options']['toolChoice'])->toBe('required');
        expect(array_map(fn($tool) => $tool->getName(), $request['options']['tools']))->toBe(['propose_plan', 'decline_refactoring']);
    });

    it('reads a decline, or an answer in prose, as nothing to refactor, in the model\'s words', function () {
        $declined = ($this->model)(new Response('', [new ToolCall('d1', 'decline_refactoring', ['reason' => 'It does one thing.'])]))->plan($this->target, '');
        $prose = ($this->model)(new Response('Nothing here is worth moving.'))->plan($this->target, '');

        expect($declined)->toBeLike(new Declined('It does one thing.'));
        expect($prose)->toBeLike(new Declined('Nothing here is worth moving.'));
    });

    it('asks for each step with the files as they now are, and reads what it writes and whether it means red', function () {
        $model = ($this->model)($this->plan, new Response('', [new ToolCall('s1', 'propose_step', [
            'files' => [
                ['path' => 'spec/App/Checkout/LoyaltyDiscount.spec.php', 'content' => "<?php // red first\n"],
                ['path' => 'src/App/Checkout/CheckoutService.php', 'content' => "<?php // changed\n"],
            ],
            'red' => true,
        ])], 800));
        $plan = $model->plan($this->target, '');

        $step = $model->step($plan, 1);

        expect($step->title)->toBe('Describe LoyaltyDiscount');
        expect($step->red)->toBeTrue();
        expect($step->tokens)->toBe(800);
        expect($step->files[0]->path)->toBe('spec/App/Checkout/LoyaltyDiscount.spec.php');
        expect($step->files[0]->isNew)->toBeTrue();
        expect($step->files[1]->old)->toBe($this->files['/proj/src/App/Checkout/CheckoutService.php']);
        expect($step->files[1]->new)->toBe("<?php // changed\n");
        $asked = end($this->replay->requests[1]['messages'])->content;
        expect($asked)->toContain('Step 2 of 2: Describe LoyaltyDiscount');
        expect($asked)->toContain("# src/App/Checkout/CheckoutService.php\n");
        expect($this->replay->requests[1]['options']['toolChoice'])->toBe(['name' => 'propose_step']);
    });

    it('refuses a step that writes outside the source and spec paths, or a file that is not PHP', function () {
        $writes = fn(string $path) => ($this->model)($this->plan, new Response('', [new ToolCall('s1', 'propose_step', ['files' => [['path' => $path, 'content' => 'x']], 'red' => false])]));

        foreach (['../outside/Evil.php' => 'src or spec', 'vendor/acme/Lib.php' => 'src or spec', 'src/App/notes.txt' => 'src or spec'] as $path => $where) {
            $model = $writes($path);
            $plan = $model->plan($this->target, '');

            expect(fn() => $model->step($plan, 0))->toThrow(RefusedStepException::class, sprintf('The step writes %s, which is not a PHP file under %s: nothing of it was written.', $path, $where));
        }
    });

    it('asks to fix a step with what the specs said about it', function () {
        $step = ['files' => [['path' => 'src/App/Checkout/CheckoutService.php', 'content' => "<?php // first try\n"]], 'red' => false];
        $model = ($this->model)($this->plan, new Response('', [new ToolCall('s1', 'propose_step', $step)]), new Response('', [new ToolCall('s2', 'propose_step', ['files' => [['path' => 'src/App/Checkout/CheckoutService.php', 'content' => "<?php // fixed\n"]], 'red' => false])]));
        $plan = $model->plan($this->target, '');

        $fixed = $model->fix($model->step($plan, 0), 'spec/App/Checkout/CheckoutService.spec.php:7  Expected 90 to be 100');

        expect($fixed->title)->toBe('Introduce a DiscountPolicy abstraction');
        expect($fixed->files[0]->new)->toBe("<?php // fixed\n");
        expect(end($this->replay->requests[2]['messages'])->content)->toContain('spec/App/Checkout/CheckoutService.spec.php:7  Expected 90 to be 100');
    });
});
