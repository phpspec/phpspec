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
use PhpSpec\Filesystem;
use Symfony\Component\Console\Output\BufferedOutput;

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

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser);
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

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser);
        $assistant->handle('write me a spec for a Calculator');

        $names = array_column($captured['tools'] ?? [], 'name');
        expect($names)->not()->toContain('generate_spec');
        expect($names)->not()->toContain('write_file');
        expect($names)->not()->toContain('add_example');
        expect($names)->toContain('read_file');
        expect($names)->toContain('run_specs');
    });

    it('refuses a write tool call while the human drives', function (Filesystem $fs) {
        $captured = null;
        $turn = 0;
        $this->provider->responder = function (array $messages) use (&$captured, &$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'generate_spec', [
                    'class_name' => 'App/Thing',
                    'spec_content' => '<?php // spec',
                ])]);
            }
            $captured = $messages;
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser);
        $assistant->handle('write a spec');

        expect($fs->write())->not()->toHaveBeenCalled();
        expect((string) json_encode($captured))->toContain('navigating');
    });

    it('blocks write tools when the chooser declines', function (Filesystem $fs) {
        $captured = null;
        $turn = 0;
        $this->provider->responder = function (array $messages) use (&$captured, &$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'generate_spec', [
                    'class_name' => 'App/Wanted',
                    'spec_content' => '<?php // spec',
                ])]);
            }
            $captured = $messages;
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives);
        $this->answers = ['3'];
        $assistant->handle('spec App/Wanted');

        expect($fs->write())->not()->toHaveBeenCalled();
        expect((string) json_encode($captured))->toContain('User declined');
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

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives);
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

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives);
        $this->answers = ['1'];
        $assistant->handle('update the checkout class');

        expect($fs->write())->toHaveBeenCalled();
    });

    it('shows a diff, not a whole-file listing, when generate_spec overwrites an existing spec', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn("<?php\ndescribe(Calculator::class, function() {\n    it(\"instantiates\", fn() => null);\n});\n");

        $turn = 0;
        $this->provider->responder = function () use (&$turn) {
            if (++$turn === 1) {
                return new Response('', [new ToolCall('t1', 'generate_spec', [
                    'class_name' => 'App/Calculator',
                    'spec_content' => "<?php\ndescribe(Calculator::class, function() {\n    it(\"instantiates\", fn() => null);\n    it(\"should total\", fn() => null);\n});\n",
                ])]);
            }
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives);
        $this->answers = ['1'];
        $assistant->handle('add a total example');

        $text = $this->buffer->fetch();
        expect($text)->toContain('[MODIFIED]');
        expect($text)->not()->toContain('[NEW FILE]');
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

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives);
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

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives);
        $this->answers = ['1'];
        $assistant->handle('add a total example');

        expect($fs->write())->not()->toBeCalled();
        expect($this->buffer->fetch())->toContain('already exists');
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

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser);
        $assistant->handle('do something odd');

        expect((string) json_encode($captured))->toContain('Unknown tool');
    });

    it('reports provider failures as an AI error', function (Filesystem $fs) {
        $this->provider->responder = function () {
            throw new RuntimeException('rate limited');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser);
        $assistant->handle('anything');

        expect($this->buffer->fetch())->toContain('AI error: rate limited');
    });

    it('stops asking about write tools after answering always, across turns', function (Filesystem $fs) {
        $turn = 0;
        $this->provider->responder = function () use (&$turn) {
            $turn++;
            if ($turn === 1) {
                return new Response('', [new ToolCall('t1', 'generate_spec', ['class_name' => 'App/First', 'spec_content' => '<?php // a'])]);
            }
            if ($turn === 3) {
                return new Response('', [new ToolCall('t2', 'generate_spec', ['class_name' => 'App/Second', 'spec_content' => '<?php // b'])]);
            }
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser, $this->aiDrives);
        // "always" on the first write; the artifact cap means one write per turn,
        // so the second spec comes on a second turn and must not ask again.
        $this->answers = ['2'];
        $assistant->handle('spec the first class');
        $assistant->handle('spec the second class');

        expect($this->reads)->toBe(1);
        expect($fs->write())->toBeCalled()->twice();
    });
});
