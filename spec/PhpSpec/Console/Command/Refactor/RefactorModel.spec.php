<?php

use PhpSpec\Ai\ModelFailedException;
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
        allow($fs->exists(any()))->toReturnUsing(fn(string $path): bool => isset($this->files[$path]));
        allow($fs->read(any()))->toReturnUsing(fn(string $path): string => $this->files[$path] ?? '');
        allow($fs->mkdir(any()));
        allow($fs->write(cetera()))->toReturnUsing(function (string $path, string $content) {
            $this->files[$path] = $content;
        });

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
    let('model', fn() => function (Response|Throwable ...$responses): RefactorModel {
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

    it('takes a plan with no steps, or an answer with neither plan nor reason, for a model that failed', function () {
        $empty = ($this->model)(new Response('', [new ToolCall('p', 'propose_plan', ['technique' => 'Extract Class', 'rationale' => 'x', 'steps' => []])]));
        $silent = ($this->model)(new Response(''));

        expect(fn() => $empty->plan($this->target, ''))->toThrow(new ModelFailedException('The model proposed a plan with no steps.'));
        expect(fn() => $silent->plan($this->target, ''))->toThrow(new ModelFailedException('The model answered with neither a plan nor a reason to decline.'));
    });

    it('says the model did not answer, in the provider\'s words, when asking it fails', function () {
        $model = ($this->model)(new RuntimeException('Google API error (400): API key not valid.'));

        expect(fn() => $model->plan($this->target, ''))->toThrow(new ModelFailedException('The model did not answer: Google API error (400): API key not valid.'));
    });

    it('asks with the model, the token limit and the effort the configuration sets', function () {
        $this->replay = new ReplayProvider([$this->plan]);
        $config = new Configuration(['ai' => ['provider' => 'google', 'api_key' => 'k', 'model' => 'gemini-x', 'max_tokens' => 2048, 'effort' => 'low']]);

        (new RefactorModel($this->replay, $config, $this->filesystem, '/proj', new PromptLibrary($this->filesystem)))->plan($this->target, '');

        $options = $this->replay->requests[0]['options'];
        expect([$options['model'], $options['maxTokens'], $options['effort']])->toBe(['gemini-x', 2048, 'low']);
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

    it('says which files the plan was made reading, each by a hash of its content', function () {
        $plan = ($this->model)($this->plan)->plan($this->target, '');

        expect($plan->read)->toBe([
            'src/App/Checkout/CheckoutService.php' => sha1($this->files['/proj/src/App/Checkout/CheckoutService.php']),
            'spec/App/Checkout/CheckoutService.spec.php' => sha1($this->files['/proj/spec/App/Checkout/CheckoutService.spec.php']),
            'src/App/Pricing/Rates.php' => sha1($this->files['/proj/src/App/Pricing/Rates.php']),
        ]);
    });

    it('takes up a plan agreed earlier, asking for its steps as though it had just proposed it', function () {
        $model = ($this->model)(new Response('', [new ToolCall('s1', 'propose_step', ['files' => [['path' => 'src/App/Checkout/DiscountPolicy.php', 'content' => "<?php // policy\n"]], 'red' => false])]));
        $plan = new RefactorPlan('Replace Conditional with Polymorphism', 'Discounts vary.', [new PlannedStep('Introduce a DiscountPolicy abstraction', 'Introducing DiscountPolicy')]);

        $model->adopt($this->target, $plan, '');
        $step = $model->step($plan, 0);

        $messages = $this->replay->requests[0]['messages'];
        expect($step->files[0]->path)->toBe('src/App/Checkout/DiscountPolicy.php');
        expect($messages[1]->content)->toContain("# src/App/Checkout/CheckoutService.php\n");
        expect($messages[2]->toolCalls[0]->name)->toBe('propose_plan');
        expect($messages[2]->toolCalls[0]->arguments)->toBe($plan->toArray());
        expect(end($messages)->content)->toStartWith('Step 1 of 1: Introduce a DiscountPolicy abstraction');

        $replayed = ReplayProvider::fromConversation(json_decode($this->files['/proj/.phpspec/ai/last-refactor.json'] ?? '', true));
        expect($replayed->chat([])->toolCalls[0]->name)->toBe('propose_plan');
        expect($replayed->chat([])->toolCalls[0]->name)->toBe('propose_step');
    });

    it('keeps the conversation as a recording that replays it, answer by answer', function () {
        $step = new Response('', [new ToolCall('s1', 'propose_step', ['files' => [['path' => 'src/App/Checkout/DiscountPolicy.php', 'content' => "<?php // policy\n"]], 'red' => false])]);
        $model = ($this->model)($this->plan, $step);
        $model->step($model->plan($this->target, ''), 0);

        $recording = json_decode($this->files['/proj/.phpspec/ai/last-refactor.json'] ?? '', true);
        $replayed = ReplayProvider::fromConversation($recording);

        expect($recording['command'])->toBe('refactor');
        expect($recording['target'])->toBe('App\\Checkout\\CheckoutService');
        expect($replayed->chat([])->toolCalls[0]->name)->toBe('propose_plan');
        expect($replayed->chat([])->toolCalls[0]->arguments['files'][0]['path'])->toBe('src/App/Checkout/DiscountPolicy.php');
    });
});
