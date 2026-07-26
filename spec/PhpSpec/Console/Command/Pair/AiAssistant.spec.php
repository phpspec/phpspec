<?php

use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\Response;
use PhpSpec\Ai\ToolCall;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Pair\AiAssistant;
use PhpSpec\Console\Command\Pair\Chooser;
use PhpSpec\Console\Command\Pair\PairOutput;
use PhpSpec\Console\Command\Pair\PairRole;
use PhpSpec\Console\Command\Pair\RoleState;
use PhpSpec\Console\Command\Pair\SpecRunner;
use PhpSpec\Console\Command\Run\RunOutcome;
use PhpSpec\Console\Command\Run\SuiteSummary;
use PhpSpec\Filesystem;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

describe(AiAssistant::class, function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->read())->toReturn('');
        allow($fs->isDir())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->scandir())->toReturn([]);

        $this->buffer = new BufferedOutput();
        $this->pairOutput = new PairOutput($this->buffer);
        $this->answers = [];
        $this->reads = 0;
        $this->chooser = new Chooser($this->pairOutput, true, function () {
            $this->reads++;
            return array_shift($this->answers) ?? '';
        });
        $this->config = new Configuration('.', $fs);

        // A fresh AI-drives role for the write-focused tests; the default (no
        // role passed) is the human driving / AI navigating, where writes are
        // withheld.
        $this->aiDrives = new RoleState(PairRole::AiDrives);

        // Hand-rolled fake: the built-in Double cannot fabricate the concrete
        // Response return type, so we script responses with a closure instead
        $this->provider = new class implements ProviderInterface {
            public ?Closure $responder = null;

            public function chat(array $messages, array $options = []): Response
            {
                return ($this->responder)($messages, $options);
            }
        };

        // Fake spec runner so run_specs and post-write auto-verify never spawn a
        // real phpspec subprocess. Tests set ->outcome to script the result; the
        // default null means auto-verify appends nothing.
        $this->specRunner = new class implements SpecRunner {
            public ?RunOutcome $outcome = null;

            public function run(string $argument, OutputInterface $output): ?RunOutcome
            {
                return $this->outcome;
            }
        };
    });

    it('passes the configured reasoning effort through to the provider', function (Filesystem $fs) {
        $yamlPath = './phpspec.yaml';
        allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath);
        allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $yamlPath ? "ai:\n  provider: google\n  api_key: k\n  effort: high\n" : '');

        $options = null;
        $this->provider->responder = function (array $messages, array $chatOptions) use (&$options) {
            $options = $chatOptions;

            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, new Configuration('.', $fs), $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        $assistant->handle('hello');

        expect($options['effort'])->toBe('high');
    });

    it('shows a navigator offer as a diff and applies it on yes', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\nnamespace App;\nclass TodoList {}\n");
        $written = [];
        allow($fs->write())->toReturnUsing(function (string $p, string $c) use (&$written) {
            $written[$p] = $c;
        });
        allow($fs->mkdir())->toReturn(null);

        $captured = null;
        $turn = 0;
        $this->provider->responder = function (array $messages) use (&$captured, &$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'offer_change', [
                    'path' => 'src/App/TodoList.php',
                    'content' => "<?php\nnamespace App;\nclass TodoList {\n    public function tasks(): array { return []; }\n}\n",
                    'intent' => 'add a tasks() accessor so the example can assert on it',
                ])]);
            }
            $captured = $messages;

            return new Response('done');
        };

        // Default role: the human drives, the AI navigates and may only offer.
        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        $this->answers = ['1'];
        $assistant->handle('how do we make this real?');

        $text = $this->buffer->fetch();
        expect($text)->toContain('add a tasks() accessor');   // the stated intent at the chooser
        expect($written)->toHaveLength(1);
        expect((string) json_encode($captured))->toContain('applied');
    });

    it('keeps a declined offer unwritten and steers the model to re-plan', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n// old");
        allow($fs->mkdir())->toReturn(null);

        $captured = null;
        $turn = 0;
        $this->provider->responder = function (array $messages) use (&$captured, &$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'offer_change', [
                    'path' => 'src/App/TodoList.php',
                    'content' => "<?php\n// new",
                    'intent' => 'rewrite it',
                ])]);
            }
            $captured = $messages;

            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        $this->answers = ['3'];
        $assistant->handle('thoughts?');

        expect($fs->write())->not()->toHaveBeenCalled();
        expect((string) json_encode($captured))->toContain('declined this offer');
    });

    it('hands the acceptance note to the model when an offer is applied', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\nnamespace App;\nclass TodoList {}\n");
        allow($fs->write())->toReturn(null);
        allow($fs->mkdir())->toReturn(null);

        $captured = null;
        $turn = 0;
        $this->provider->responder = function (array $messages) use (&$captured, &$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'offer_change', [
                    'path' => 'src/App/TodoList.php',
                    'content' => "<?php\nnamespace App;\nclass TodoList {\n    public function items(): array { return []; }\n}\n",
                    'intent' => 'add an accessor',
                ])]);
            }
            $captured = $messages;

            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        $this->answers = ['1, rename the method to tasks'];
        $assistant->handle('how do we make this real?');

        $text = (string) json_encode($captured);
        expect($text)->toContain('The human added');
        expect($text)->toContain('rename the method to tasks');
    });

    it('hands the decline note to the model as the direction to take instead', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n// old");
        allow($fs->mkdir())->toReturn(null);

        $captured = null;
        $turn = 0;
        $this->provider->responder = function (array $messages) use (&$captured, &$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'offer_change', [
                    'path' => 'src/App/TodoList.php',
                    'content' => "<?php\n// new",
                    'intent' => 'rewrite it',
                ])]);
            }
            $captured = $messages;

            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        $this->answers = ['3, make it a Feature instead'];
        $assistant->handle('thoughts?');

        expect($fs->write())->not()->toHaveBeenCalled();
        $text = (string) json_encode($captured);
        expect($text)->toContain('make it a Feature instead');
        expect($text)->toContain('Address that');
    });

    it('rejects an offer that would drop spec examples, before any chooser', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\ndescribe('TodoList', function () {\n    it(\"should add\", fn() => expect(null)->toBe(null));\n    it(\"should tasks\", fn() => expect(null)->toBe(null));\n});\n");

        $captured = null;
        $turn = 0;
        $this->provider->responder = function (array $messages) use (&$captured, &$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'offer_change', [
                    'path' => 'spec/App/TodoList.spec.php',
                    'content' => "<?php\ndescribe('TodoList', function () {\n    it('keeps track of added tasks', fn() => expect(true)->toBe(true));\n});\n",
                    'intent' => 'replace the anemic examples',
                ])]);
            }
            $captured = $messages;

            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        $this->answers = ['1'];
        $assistant->handle('sharpen the spec');

        expect($fs->write())->not()->toHaveBeenCalled();
        expect((string) json_encode($captured))->toContain('drop existing examples');
    });

    it('offers replace writes while navigating, and writes replace offers while driving', function (Filesystem $fs) {
        $names = null;
        $this->provider->responder = function (array $messages, array $options) use (&$names) {
            $names = array_column($options['tools'], 'name');

            return new Response('done');
        };

        $navigator = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        $navigator->handle('hello');
        expect($names)->toContain('offer_change');
        expect($names)->not()->toContain('write_file');

        $driver = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives, $this->specRunner);
        $driver->handle('hello');
        expect($names)->toContain('write_file');
        expect($names)->not()->toContain('offer_change');
    });

    it('allows at most one offer per turn, withholding and refusing the second', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n// old");
        allow($fs->mkdir())->toReturn(null);
        $written = [];
        allow($fs->write())->toReturnUsing(function (string $p, string $c) use (&$written) {
            $written[$p] = $c;
        });

        $secondTurnTools = null;
        $captured = null;
        $turn = 0;
        $this->provider->responder = function (array $messages, array $options) use (&$secondTurnTools, &$captured, &$turn) {
            if (++$turn === 1) {
                return new Response('', [
                    new ToolCall('t1', 'offer_change', ['path' => 'src/A.php', 'content' => "<?php\n// first", 'intent' => 'first change']),
                    new ToolCall('t2', 'offer_change', ['path' => 'src/B.php', 'content' => "<?php\n// second", 'intent' => 'second change']),
                ]);
            }
            $secondTurnTools = array_column($options['tools'], 'name');
            $captured = $messages;

            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        $this->answers = ['1'];
        $assistant->handle('thoughts?');

        expect($written)->toHaveLength(1);                                 // only the first offer landed
        expect((string) json_encode($captured))->toContain('One offer per turn');
        expect($secondTurnTools)->not()->toContain('offer_change');        // and none advertised after
    });

    it('registers at most one suggestion per turn, ending the advice in prose', function (Filesystem $fs) {
        $secondTurnTools = null;
        $turn = 0;
        $this->provider->responder = function (array $messages, array $options) use (&$secondTurnTools, &$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'suggest_next', ['type' => 'spec', 'target' => 'App\\First', 'reason' => 'one'])]);
            }
            $secondTurnTools = array_column($options['tools'], 'name');

            return new Response('My advice in prose.');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        $assistant->handle('what next?');

        expect($assistant->lastSuggestion()['target'])->toBe('App\\First');
        expect($secondTurnTools)->not()->toContain('suggest_next');        // one registration per turn
    });

    it('registers the model suggestion for the dispatcher ghost', function (Filesystem $fs) {
        $turn = 0;
        $this->provider->responder = function () use (&$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'suggest_next', [
                    'type' => 'example',
                    'target' => 'App\\TodoList',
                    'reason' => 'The current examples assert null.',
                ])]);
            }

            return new Response('Let us make the example real.');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        expect($assistant->lastSuggestion())->toBeNull();

        $assistant->handle('what next?');

        expect($assistant->lastSuggestion())->toBe(['type' => 'example', 'target' => 'App\\TodoList', 'reason' => 'The current examples assert null.']);
    });

    it('routes ask_user tool calls from the model through the chooser', function (Filesystem $fs) {
        $captured = null;
        $turn = 0;
        $this->provider->responder = function (array $messages) use (&$captured, &$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'ask_user', [
                    'question' => 'Do you want me to generate the method fooBar()?',
                    'action' => 'generate methods',
                ])]);
            }
            $captured = $messages;
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        $this->answers = ['2'];
        $assistant->handle('add a fooBar method');

        $text = $this->buffer->fetch();
        expect($text)->toContain('Do you want me to generate the method fooBar()?');
        expect($text)->toContain('1. Yes');
        expect((string) json_encode($captured))->toContain('always');
    });

    it('does not advertise write tools while the human drives', function (Filesystem $fs) {
        $captured = null;
        $this->provider->responder = function (array $messages, array $options) use (&$captured) {
            $captured = $options;
            return new Response('ok');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        $assistant->handle('write me a spec for a Calculator');

        $names = array_column($captured['tools'] ?? [], 'name');
        expect($names)->not()->toContain('describe');
        expect($names)->not()->toContain('write_file');
        expect($names)->not()->toContain('add_example');
        expect($names)->toContain('read_file');
        expect($names)->toContain('run_specs');
        // Read tools like inspect_symbol stay available while navigating.
        expect($names)->toContain('inspect_symbol');
    });

    it('refuses a write tool call while the human drives', function (Filesystem $fs) {
        $captured = null;
        $turn = 0;
        $this->provider->responder = function (array $messages) use (&$captured, &$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'describe', [
                    'class_name' => 'App/Thing',
                ])]);
            }
            $captured = $messages;
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        $assistant->handle('write a spec');

        expect($fs->write())->not()->toHaveBeenCalled();
        expect((string) json_encode($captured))->toContain('navigating');
        // A refused write is not displayed as if it ran.
        expect($this->buffer->fetch())->not()->toContain('Describe');
    });

    it('blocks write tools when the chooser declines', function (Filesystem $fs) {
        $captured = null;
        $turn = 0;
        $this->provider->responder = function (array $messages) use (&$captured, &$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'describe', [
                    'class_name' => 'App/Wanted',
                ])]);
            }
            $captured = $messages;
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives, $this->specRunner);
        $this->answers = ['3'];
        $assistant->handle('spec App/Wanted');

        expect($fs->write())->not()->toHaveBeenCalled();
        // Declining steers the driver back into the cycle, rather than a bare "declined".
        expect((string) json_encode($captured))->toContain('declined this step');
    });

    it('hands an ask_user note back to the model with the answer', function (Filesystem $fs) {
        $captured = null;
        $turn = 0;
        $this->provider->responder = function (array $messages) use (&$captured, &$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'ask_user', [
                    'question' => 'Shall I generate fooBar()?',
                    'action' => 'generate methods',
                ])]);
            }
            $captured = $messages;

            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        $this->answers = ['3, name it barFoo instead'];
        $assistant->handle('what next?');

        $text = (string) json_encode($captured);
        expect($text)->toContain('no. The human added');
        expect($text)->toContain('name it barFoo instead');
    });

    it('hands the confirm-step notes to the driving model, on yes and on no', function (Filesystem $fs) {
        $captured = null;
        $turn = 0;
        $this->provider->responder = function (array $messages) use (&$captured, &$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'describe', [
                    'class_name' => 'App/Wanted',
                ])]);
            }
            $captured = $messages;

            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives, $this->specRunner);
        $this->answers = ['3, exemplify addTask first'];
        $assistant->handle('spec App/Wanted');

        $text = (string) json_encode($captured);
        expect($text)->toContain('exemplify addTask first');
        expect($text)->toContain('Address that');

        $captured = null;
        $turn = 0;
        allow($fs->write())->toReturn(null);
        allow($fs->mkdir())->toReturn(null);
        $accepting = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, new RoleState(PairRole::AiDrives), $this->specRunner);
        $this->answers = ['1, then run it'];
        $accepting->handle('spec App/Wanted');

        $text = (string) json_encode($captured);
        expect($text)->toContain('The human added');
        expect($text)->toContain('then run it');
    });

    it('ends the driver turn after one artifact instead of chasing a bigger goal', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\ndescribe(A::class, function () {});\n");

        $calls = 0;
        $this->provider->responder = function () use (&$calls) {
            $calls++;
            if ($calls === 1) {
                return new Response('', [new ToolCall('t1', 'add_example', [
                    'class_name' => 'App/A',
                    'method' => 'sum',
                ])]);
            }

            // The model keeps reaching for more writes to "finish the goal".
            return new Response('', [new ToolCall('t' . $calls, 'write_file', [
                'path' => 'src/App/A.php',
                'content' => '<?php // more',
            ])]);
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives, $this->specRunner);
        $this->answers = ['1'];
        $assistant->handle('get to green');

        // Exactly one artifact landed, and the turn handed back promptly rather
        // than thrashing to the tool-turn ceiling.
        expect($fs->write())->toBeCalled()->once();
        expect($calls)->toBeLessThan(4);
        expect($this->buffer->fetch())->toContain('one change');
    });

    it('rejects phpspec 8 ObjectBehavior syntax written to a spec path via update_file', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\ndescribe(Wallet::class, function () {});\n");

        $captured = null;
        $turn = 0;
        $this->provider->responder = function (array $messages) use (&$captured, &$turn) {
            if (++$turn === 1) {
                // The DSL guard applies to any write under the spec path, closing the
                // write_file/update_file bypass of the describe/add_example surface.
                return new Response('', [new ToolCall('t1', 'update_file', [
                    'path' => 'spec/App/Wallet.spec.php',
                    'content' => "<?php\n\nnamespace spec\\App;\n\nuse App\\Wallet;\nuse PhpSpec\\ObjectBehavior;\n\nclass WalletSpec extends ObjectBehavior\n{\n    function it_is_initializable()\n    {\n        \$this->shouldHaveType(Wallet::class);\n    }\n}\n",
                ])]);
            }
            $captured = $messages;
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives, $this->specRunner);
        $this->answers = ['1'];
        $assistant->handle('bring it back to green');

        expect($fs->write())->not()->toHaveBeenCalled();
        expect((string) json_encode($captured))->toContain('describe(');
    });

    it('rejects shouldXxx spec content even without the ObjectBehavior literal', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\ndescribe(Basket::class, function () {});\n");

        $captured = null;
        $turn = 0;
        $this->provider->responder = function (array $messages) use (&$captured, &$turn) {
            if (++$turn === 1) {
                // The E5-shaped reply: modern-looking describe() wrapping the old
                // matcher DSL. The shared detector catches it, not just the literal.
                return new Response('', [new ToolCall('t1', 'update_file', [
                    'path' => 'spec/App/Basket.spec.php',
                    'content' => "<?php\ndescribe('Basket', function () { it('adds', function () { \$this->add(2, 3)->shouldReturn(5); }); });\n",
                ])]);
            }
            $captured = $messages;

            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives, $this->specRunner);
        $this->answers = ['1'];
        $assistant->handle('bring it back to green');

        expect($fs->write())->not()->toHaveBeenCalled();
        // The rejection reached the model as a tool error (not a swallowed crash),
        // steering it back to the phpspec 9 DSL.
        expect((string) json_encode($captured))->toContain('phpspec 9');
    });

    it('writes only one artifact per turn while driving, rejecting the rest', function (Filesystem $fs) {
        $captured = null;
        $secondTurnMessages = null;
        $turn = 0;
        $this->provider->responder = function (array $messages, array $options) use (&$captured, &$secondTurnMessages, &$turn) {
            if (++$turn === 1) {
                return new Response('', [
                    new ToolCall('t1', 'generate_feature', ['feature_name' => 'checkout', 'content' => 'Feature: Checkout']),
                    new ToolCall('t2', 'generate_steps', ['feature_name' => 'checkout', 'content' => '<?php // steps']),
                    new ToolCall('t3', 'write_file', ['path' => 'src/App/Checkout.php', 'content' => '<?php // class']),
                ]);
            }
            $captured = $options;
            $secondTurnMessages = $messages;
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives, $this->specRunner);
        $this->answers = ['2'];
        $assistant->handle('build the checkout feature');

        // Only the first artifact is written; the other two are rejected.
        expect($fs->write())->toBeCalled()->once();
        expect((string) json_encode($secondTurnMessages))->toContain('One artifact per turn');

        // Once an artifact is written, no further write tools are advertised this turn.
        $names = array_column($captured['tools'] ?? [], 'name');
        expect($names)->not()->toContain('generate_feature');
        expect($names)->not()->toContain('write_file');
        expect($names)->toContain('run_specs');
    });

    it('updates an existing file showing what changed', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\n// old body\n");

        $turn = 0;
        $this->provider->responder = function () use (&$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'update_file', [
                    'path' => 'src/App/Checkout.php',
                    'content' => "<?php\n// new body\n",
                ])]);
            }
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives, $this->specRunner);
        $this->answers = ['1'];
        $assistant->handle('update the checkout class');

        expect($fs->write())->toHaveBeenCalled();
    });

    it('rejects a spec overwrite via update_file that would drop existing examples', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        // The spec on disk has two examples; the rewrite keeps only one.
        allow($fs->read())->toReturn("<?php\ndescribe(Calculator::class, function() {\n    it(\"adds\", fn() => null);\n    it(\"subtracts\", fn() => null);\n});\n");

        $captured = null;
        $turn = 0;
        $this->provider->responder = function (array $messages) use (&$captured, &$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'update_file', [
                    'path' => 'spec/App/Calculator.spec.php',
                    'content' => "<?php\ndescribe(Calculator::class, function() {\n    it(\"adds\", fn() => null);\n});\n",
                ])]);
            }
            $captured = $messages;
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives, $this->specRunner);
        $this->answers = ['1'];
        $assistant->handle('trim the calculator spec');

        expect($fs->write())->not()->toHaveBeenCalled();
        expect((string) json_encode($captured))->toContain('add_example');
    });

    it('appends a single example with add_example instead of rewriting the whole spec', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\ndescribe(Calculator::class, function() {\n    it(\"instantiates\", fn() => null);\n});\n");
        $written = null;
        allow($fs->write())->toReturnUsing(function (string $path, string $content) use (&$written) {
            $written = $content;
        });

        $turn = 0;
        $this->provider->responder = function () use (&$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'add_example', [
                    'class_name' => 'App/Calculator',
                    'method' => 'total',
                ])]);
            }
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives, $this->specRunner);
        $this->answers = ['1'];
        $assistant->handle('add a total example to the calculator spec');

        $text = $this->buffer->fetch();
        expect($text)->toContain('[MODIFIED]');
        expect($text)->not()->toContain('[NEW FILE]');
        expect($written)->toContain('should total');
        expect($written)->toContain('instantiates');
    });

    it('does not duplicate an example when add_example targets an already-exemplified method', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\ndescribe(Calculator::class, function() {\n    it(\"instantiates\", fn() => null);\n    it(\"should total\", fn() => null);\n});\n");

        $turn = 0;
        $this->provider->responder = function () use (&$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'add_example', [
                    'class_name' => 'App/Calculator',
                    'method' => 'total',
                ])]);
            }
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives, $this->specRunner);
        $this->answers = ['1'];
        $assistant->handle('add a total example');

        expect($fs->write())->not()->toBeCalled();
        expect($this->buffer->fetch())->toContain('already exists');
    });

    it('starts a new spec with describe, writing a skeleton', function (Filesystem $fs) {
        $turn = 0;
        $this->provider->responder = function () use (&$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'describe', ['class_name' => 'App/Ledger'])]);
            }
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives, $this->specRunner);
        $this->answers = ['1'];
        $assistant->handle('start a ledger spec');

        expect($fs->write())->toHaveBeenCalled();
    });

    it('is idempotent: describe on an existing spec writes nothing', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\ndescribe(Ledger::class, function () {});\n");

        $turn = 0;
        $this->provider->responder = function () use (&$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'describe', ['class_name' => 'App/Ledger'])]);
            }
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives, $this->specRunner);
        $this->answers = ['1'];
        $assistant->handle('describe the ledger');

        expect($fs->write())->not()->toBeCalled();
        expect($this->buffer->fetch())->toContain('already exists');
    });

    it('allows a source write via write_file, unaffected by the spec guard', function (Filesystem $fs) {
        $turn = 0;
        $this->provider->responder = function () use (&$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'write_file', [
                    'path' => 'src/App/Ledger.php',
                    'content' => "<?php\n\nnamespace App;\n\nclass Ledger {}\n",
                ])]);
            }
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives, $this->specRunner);
        $this->answers = ['1'];
        $assistant->handle('create the Ledger class');

        expect($fs->write())->toHaveBeenCalled();
    });

    it('shows the driver\'s stated intent at the confirm prompt', function (Filesystem $fs) {
        $turn = 0;
        $this->provider->responder = function () use (&$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'describe', [
                    'class_name' => 'App/Ledger',
                    'intent' => 'start a spec that pins down how the ledger records entries',
                ])]);
            }
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives, $this->specRunner);
        $this->answers = ['1'];
        $assistant->handle('describe the ledger');

        expect($this->buffer->fetch())->toContain('Plan: start a spec that pins down how the ledger records entries');
    });

    it('grounds a symbol via inspect_symbol, reporting its real signatures instead of guessing', function (Filesystem $fs) {
        $captured = null;
        $turn = 0;
        $this->provider->responder = function (array $messages) use (&$captured, &$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'inspect_symbol', [
                    'fqcn' => 'PhpSpec\\CodeGeneration\\ClassLocation',
                ])]);
            }
            $captured = $messages;
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        $assistant->handle('what does ClassLocation offer?');

        // The tool result carries the real class surface, not a guess or a "File not found".
        $json = (string) json_encode($captured);
        expect($json)->toContain('class PhpSpec');
        expect($json)->toContain('isAutoloadable');
    });

    it('reports the structured red/green state from run_specs, not raw stdout', function (Filesystem $fs) {
        $this->specRunner->outcome = new RunOutcome(null, new SuiteSummary(
            'red',
            ['examples' => 1, 'passes' => 0, 'failures' => 1, 'errors' => 0, 'pending' => 0],
            [['subject' => 'App\\Calculator', 'example' => 'adds numbers', 'error' => 'Expected 5 but got 3']],
        ));

        $captured = null;
        $turn = 0;
        $this->provider->responder = function (array $messages) use (&$captured, &$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'run_specs', ['path' => ''])]);
            }
            $captured = $messages;
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        $assistant->handle('run the specs');

        $json = (string) json_encode($captured);
        expect($json)->toContain('SUITE: red');
        expect($json)->toContain('adds numbers');
        expect($json)->toContain('Expected 5 but got 3');
    });

    it('auto-verifies after the driver writes, feeding the fresh state back to the model', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\ndescribe(Calculator::class, function () {});\n");

        $this->specRunner->outcome = new RunOutcome(null, new SuiteSummary(
            'green',
            ['examples' => 1, 'passes' => 1, 'failures' => 0, 'errors' => 0, 'pending' => 0],
        ));

        $secondTurnMessages = null;
        $turn = 0;
        $this->provider->responder = function (array $messages) use (&$secondTurnMessages, &$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'add_example', [
                    'class_name' => 'App/Calculator',
                    'method' => 'total',
                ])]);
            }
            $secondTurnMessages = $messages;
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives, $this->specRunner);
        $this->answers = ['1'];
        $assistant->handle('spec the calculator');

        $json = (string) json_encode($secondTurnMessages);
        expect($json)->toContain('[Auto-verify after your change]');
        expect($json)->toContain('SUITE: green');
    });

    it('does not auto-verify a write the navigator role refused', function (Filesystem $fs) {
        $this->specRunner->outcome = new RunOutcome(null, new SuiteSummary('green'));

        $secondTurnMessages = null;
        $turn = 0;
        $this->provider->responder = function (array $messages) use (&$secondTurnMessages, &$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'describe', [
                    'class_name' => 'App/Thing',
                ])]);
            }
            $secondTurnMessages = $messages;
            return new Response('done');
        };

        // No role passed: the human drives, so the write is refused and nothing lands.
        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        $assistant->handle('write a spec');

        $json = (string) json_encode($secondTurnMessages);
        expect($json)->toContain('navigating');
        expect($json)->not()->toContain('[Auto-verify');
    });

    it('reports unknown tools back to the model instead of crashing', function (Filesystem $fs) {
        $captured = null;
        $turn = 0;
        $this->provider->responder = function (array $messages) use (&$captured, &$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'no_such_tool', [])]);
            }
            $captured = $messages;
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        $assistant->handle('do something odd');

        expect((string) json_encode($captured))->toContain('Unknown tool');
    });

    it('reports provider failures as an AI error', function (Filesystem $fs) {
        $this->provider->responder = function () {
            throw new RuntimeException('rate limited');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        $assistant->handle('anything');

        expect($this->buffer->fetch())->toContain('AI error: rate limited');
    });

    it('grounds the turn in the real suite state, before the input, when a summary is supplied', function (Filesystem $fs) {
        $captured = null;
        $this->provider->responder = function (array $messages) use (&$captured) {
            $captured = $messages;
            return new Response('ok');
        };

        $summary = new SuiteSummary(
            'red',
            ['examples' => 1, 'passes' => 0, 'failures' => 1, 'errors' => 0, 'pending' => 0],
            [['subject' => 'App\\Calculator', 'example' => 'adds numbers', 'error' => 'Expected 5 but got 3']],
        );

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        $assistant->handle('what should we do next?', $summary);

        $json = (string) json_encode($captured);
        expect($json)->toContain('[Current situation]');
        expect($json)->toContain('FAILING');
        expect($json)->toContain('adds numbers');
        expect($json)->toContain('Expected 5 but got 3');

        // The grounding is injected before the input, so the model reads state first.
        expect(strpos($json, '[Current situation]'))->toBeLessThan(strpos($json, 'what should we do next?'));
    });

    it('does not accumulate stale [Current situation] grounding across turns', function (Filesystem $fs) {
        $captured = null;
        $this->provider->responder = function (array $messages) use (&$captured) {
            $captured = $messages;
            return new Response('ok');
        };

        $red = new SuiteSummary('red', ['examples' => 1, 'passes' => 0, 'failures' => 1, 'errors' => 0, 'pending' => 0], [['subject' => 'App\\C', 'example' => 'adds', 'error' => 'boom']]);
        $green = new SuiteSummary('green', ['examples' => 1, 'passes' => 1, 'failures' => 0, 'errors' => 0, 'pending' => 0]);

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        $assistant->handle('turn one', $red);
        $assistant->handle('turn two', $green);

        // Only the latest situation survives in what the model sees.
        expect(substr_count((string) json_encode($captured), '[Current situation]'))->toBe(1);
    });

    it('injects no situation message when no summary is supplied', function (Filesystem $fs) {
        $captured = null;
        $this->provider->responder = function (array $messages) use (&$captured) {
            $captured = $messages;
            return new Response('ok');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        $assistant->handle('just chatting');

        expect((string) json_encode($captured))->not()->toContain('[Current situation]');
    });

    it('asks the provider for the generous default token ceiling', function (Filesystem $fs) {
        $captured = null;
        $this->provider->responder = function (array $messages, array $options) use (&$captured) {
            $captured = $options;
            return new Response('ok');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, null, $this->specRunner);
        $assistant->handle('anything');

        expect($captured['maxTokens'])->toBe(16384);
    });

    it('stops asking about write tools after answering always, across turns', function (Filesystem $fs) {
        $turn = 0;
        $this->provider->responder = function () use (&$turn) {
            $turn++;
            if ($turn === 1) {
                return new Response('', [new ToolCall('t1', 'describe', ['class_name' => 'App/First'])]);
            }
            if ($turn === 3) {
                return new Response('', [new ToolCall('t2', 'describe', ['class_name' => 'App/Second'])]);
            }
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives, $this->specRunner);
        // "always" on the first write; the artifact cap means one write per turn,
        // so the second spec comes on a second turn and must not ask again.
        $this->answers = ['2'];
        $assistant->handle('spec the first class');
        $assistant->handle('spec the second class');

        expect($this->reads)->toBe(1);
        expect($fs->write())->toBeCalled()->twice();
    });
});
