<?php

use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\Response;
use PhpSpec\Ai\ToolCall;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Pair\AiAssistant;
use PhpSpec\Console\Command\Pair\Chooser;
use PhpSpec\Console\Command\Pair\PairOutput;
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

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser);
        $this->answers = ['3'];
        $assistant->handle('spec App/Wanted');

        expect($fs->write())->not()->toHaveBeenCalled();
        expect((string) json_encode($captured))->toContain('User declined');
    });

    it('executes feature, steps and file tools once approved', function (Filesystem $fs) {
        $turn = 0;
        $this->provider->responder = function () use (&$turn) {
            if (++$turn === 1) {
                return new Response('', [
                    new ToolCall('t1', 'generate_feature', ['feature_name' => 'checkout', 'content' => 'Feature: Checkout']),
                    new ToolCall('t2', 'generate_steps', ['feature_name' => 'checkout', 'content' => '<?php // steps']),
                    new ToolCall('t3', 'write_file', ['path' => 'src/App/Checkout.php', 'content' => '<?php // class']),
                ]);
            }
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser);
        $this->answers = ['2'];
        $assistant->handle('build the checkout feature');

        expect($this->reads)->toBe(1);
        expect($fs->write())->toBeCalled()->exactly(3)->times();
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

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser);
        $this->answers = ['1'];
        $assistant->handle('update the checkout class');

        expect($fs->write())->toHaveBeenCalled();
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

    it('stops asking about write tools after answering always', function (Filesystem $fs) {
        $turn = 0;
        $this->provider->responder = function () use (&$turn) {
            if (++$turn === 1) {
                return new Response('', [
                    new ToolCall('t1', 'generate_spec', ['class_name' => 'App/First', 'spec_content' => '<?php // a']),
                    new ToolCall('t2', 'generate_spec', ['class_name' => 'App/Second', 'spec_content' => '<?php // b']),
                ]);
            }
            return new Response('done');
        };

        $assistant = new AiAssistant($this->provider, $this->config, $this->pairOutput, 'test-model', $fs, true, null, $this->chooser);
        $this->answers = ['2'];
        $assistant->handle('spec both classes');

        expect($this->reads)->toBe(1);
        expect($fs->write())->toBeCalled()->twice();
    });
});
