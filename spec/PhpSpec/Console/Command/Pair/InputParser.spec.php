<?php

use PhpSpec\Console\Command\Pair\InputParser;

describe(InputParser::class, function () {
    let('parser', fn() => new InputParser());

    it('instantiates', fn() => expect($this->parser)->toBeAnInstanceOf(InputParser::class));

    it('parses describe with class argument', function () {
        $result = $this->parser->parse('describe Acme\Greeter');
        expect($result)->toBe(['command' => 'describe', 'argument' => 'Acme\Greeter']);
    });

    it('parses /help with no argument', function () {
        $result = $this->parser->parse('/help');
        expect($result)->toBe(['command' => '/help', 'argument' => '']);
    });

    it('parses /quit with no argument', function () {
        $result = $this->parser->parse('/quit');
        expect($result)->toBe(['command' => '/quit', 'argument' => '']);
    });

    it('parses run with no argument', function () {
        $result = $this->parser->parse('run');
        expect($result)->toBe(['command' => 'run', 'argument' => '']);
    });

    it('parses run with a path argument', function () {
        $result = $this->parser->parse('run spec/Acme');
        expect($result)->toBe(['command' => 'run', 'argument' => 'spec/Acme']);
    });

    it('handles leading whitespace', function () {
        $result = $this->parser->parse('  describe   Acme\Greeter');
        expect($result)->toBe(['command' => 'describe', 'argument' => 'Acme\Greeter']);
    });

    it('returns empty command for empty input', function () {
        $result = $this->parser->parse('');
        expect($result)->toBe(['command' => '', 'argument' => '']);
    });

    it('returns empty command for whitespace-only input', function () {
        $result = $this->parser->parse('   ');
        expect($result)->toBe(['command' => '', 'argument' => '']);
    });

    it('lowercases the command part', function () {
        $result = $this->parser->parse('DESCRIBE Acme\Greeter');
        expect($result)->toBe(['command' => 'describe', 'argument' => 'Acme\Greeter']);
    });

    it('parses /exit command', function () {
        $result = $this->parser->parse('/exit');
        expect($result)->toBe(['command' => '/exit', 'argument' => '']);
    });

    it('parses clear command', function () {
        $result = $this->parser->parse('clear');
        expect($result)->toBe(['command' => 'clear', 'argument' => '']);
    });

    it('collects all non-option tokens as the argument', function () {
        $result = $this->parser->parse('exemplify App\Calculator add');
        expect($result)->toBe(['command' => 'exemplify', 'argument' => 'App\Calculator add']);
    });

    it('skips option flags between tokens', function () {
        $result = $this->parser->parse('describe PhpSpec\Newton -e gravitate');
        expect($result)->toBe(['command' => 'describe', 'argument' => 'PhpSpec\Newton gravitate']);
    });

    it('skips option flags before the argument', function () {
        $result = $this->parser->parse('describe -e PhpSpec\Greeter greet');
        expect($result)->toBe(['command' => 'describe', 'argument' => 'PhpSpec\Greeter greet']);
    });

    it('skips long option flags before the argument', function () {
        $result = $this->parser->parse('run --filter spec/Acme');
        expect($result)->toBe(['command' => 'run', 'argument' => 'spec/Acme']);
    });

    it('returns empty argument when only options are present', function () {
        $result = $this->parser->parse('run --stop-on-failure');
        expect($result)->toBe(['command' => 'run', 'argument' => '']);
    });
});
