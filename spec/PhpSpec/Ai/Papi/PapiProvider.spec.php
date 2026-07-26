<?php

use PhpSpec\Ai\Message;
use PhpSpec\Ai\Papi\PapiProvider;
use PhpSpec\Ai\Tool;

// The adapter's whole job is that PhpSpec code never references PapiAI
// directly, and papi providers only consume tool declarations in ARRAY shape
// (objects are silently dropped, and the wire then carries zero tools). The
// examples pin the conversion at the seam, where no eval replay can see it.
describe(PapiProvider::class, function () {

    beforeEach(function () {
        $this->papi = new class implements \PapiAI\Core\Contracts\ProviderInterface {
            /** @var array<string, mixed> */
            public array $options = [];

            public function chat(array $messages, array $options = []): \PapiAI\Core\Response
            {
                $this->options = $options;

                return new \PapiAI\Core\Response(text: 'ok');
            }

            public function stream(array $messages, array $options = []): iterable
            {
                return [];
            }

            public function supportsTool(): bool
            {
                return true;
            }

            public function supportsVision(): bool
            {
                return false;
            }

            public function supportsStructuredOutput(): bool
            {
                return false;
            }

            public function getName(): string
            {
                return 'fake';
            }
        };
        $this->provider = new PapiProvider($this->papi);
    });

    it('serialises Tool objects into the array shape papi providers consume', function () {
        $tool = Tool::make(
            name: 'suggest_next',
            description: 'Register the next step.',
            parameters: ['target' => ['type' => 'string', 'description' => 'the subject']],
            handler: static fn(array $a): string => '',
        );

        $this->provider->chat([Message::user('hi')], ['tools' => [$tool]]);

        $sent = $this->papi->options['tools'][0];
        expect(is_array($sent))->toBeTrue();
        expect($sent['name'])->toBe('suggest_next');
        expect($sent['description'])->toBe('Register the next step.');
        expect($sent['input_schema']['type'])->toBe('object');
        expect($sent['input_schema']['properties']['target']['type'])->toBe('string');
    });

    it('passes already-serialised tool arrays through untouched', function () {
        $asArray = ['name' => 'run_specs', 'description' => 'Run.', 'input_schema' => ['type' => 'object', 'properties' => []]];

        $this->provider->chat([Message::user('hi')], ['tools' => [$asArray]]);

        expect($this->papi->options['tools'][0])->toBe($asArray);
    });

    it('leaves the other options, toolChoice included, intact', function () {
        $this->provider->chat([Message::user('hi')], ['toolChoice' => ['name' => 'suggest_next'], 'maxTokens' => 64]);

        expect($this->papi->options['toolChoice'])->toBe(['name' => 'suggest_next']);
        expect($this->papi->options['maxTokens'])->toBe(64);
    });

});
