<?php

use PhpSpec\Console\Command\Pair\InputParser;

describe(InputParser::class, function () {
    let('parser', fn() => new InputParser());

    it('instantiates', fn() => expect($this->parser)->toBeAnInstanceOf(InputParser::class));

    it('parses /help with no argument', function () {
        $result = $this->parser->parse('/help');
        expect($result)->toBe(['command' => '/help', 'argument' => '', 'tail' => '']);
    });

    it('parses /quit with no argument', function () {
        $result = $this->parser->parse('/quit');
        expect($result)->toBe(['command' => '/quit', 'argument' => '', 'tail' => '']);
    });

    it('parses /exit command', function () {
        $result = $this->parser->parse('/exit');
        expect($result)->toBe(['command' => '/exit', 'argument' => '', 'tail' => '']);
    });

    it('returns empty command for empty input', function () {
        $result = $this->parser->parse('');
        expect($result)->toBe(['command' => '', 'argument' => '', 'tail' => '']);
    });

    it('returns empty command for whitespace-only input', function () {
        $result = $this->parser->parse('   ');
        expect($result)->toBe(['command' => '', 'argument' => '', 'tail' => '']);
    });

    it('keeps the raw tail of a slash command, options in their place', function () {
        $result = $this->parser->parse('/run --filter spec/Acme');
        expect($result)->toBe(['command' => '/run', 'argument' => 'spec/Acme', 'tail' => '--filter spec/Acme']);
    });

    context('command words route without a slash when the line reads as a command', function () {
        it('routes describe with a class argument', function () {
            $result = $this->parser->parse('describe Acme\Greeter');
            expect($result)->toBe(['command' => '/describe', 'argument' => 'Acme\Greeter', 'tail' => 'Acme\Greeter']);
        });

        it('routes describe with a slash-path class', function () {
            $result = $this->parser->parse('describe App/Basket');
            expect($result)->toBe(['command' => '/describe', 'argument' => 'App/Basket', 'tail' => 'App/Basket']);
        });

        it('lowercases the command word before routing', function () {
            $result = $this->parser->parse('DESCRIBE Acme\Greeter');
            expect($result)->toBe(['command' => '/describe', 'argument' => 'Acme\Greeter', 'tail' => 'Acme\Greeter']);
        });

        it('handles leading whitespace', function () {
            $result = $this->parser->parse('  describe   Acme\Greeter');
            expect($result)->toBe(['command' => '/describe', 'argument' => 'Acme\Greeter', 'tail' => 'Acme\Greeter']);
        });

        it('routes exemplify with a class and method', function () {
            $result = $this->parser->parse('exemplify App\Calculator add');
            expect($result)->toBe(['command' => '/exemplify', 'argument' => 'App\Calculator add', 'tail' => 'App\Calculator add']);
        });

        it('routes refactor with a class target', function () {
            $result = $this->parser->parse('refactor Todo');
            expect($result)->toBe(['command' => '/refactor', 'argument' => 'Todo', 'tail' => 'Todo']);
        });

        it('routes refactor with a method target', function () {
            $result = $this->parser->parse('refactor App\Todo::addTask');
            expect($result)->toBe(['command' => '/refactor', 'argument' => 'App\Todo::addTask', 'tail' => 'App\Todo::addTask']);
        });

        it('routes a bare run', function () {
            $result = $this->parser->parse('run');
            expect($result)->toBe(['command' => '/run', 'argument' => '', 'tail' => '']);
        });

        it('routes run with a path argument', function () {
            $result = $this->parser->parse('run spec/Acme');
            expect($result)->toBe(['command' => '/run', 'argument' => 'spec/Acme', 'tail' => 'spec/Acme']);
        });

        it('routes run with a feature file argument', function () {
            $result = $this->parser->parse('run adding.feature');
            expect($result)->toBe(['command' => '/run', 'argument' => 'adding.feature', 'tail' => 'adding.feature']);
        });

        it('routes run with a suite keyword', function () {
            $result = $this->parser->parse('run features');
            expect($result)->toBe(['command' => '/run', 'argument' => 'features', 'tail' => 'features']);
        });

        it('routes run with only options', function () {
            $result = $this->parser->parse('run --all');
            expect($result)->toBe(['command' => '/run', 'argument' => '', 'tail' => '--all']);
        });

        it('routes run with an option and a path, keeping the tail order', function () {
            $result = $this->parser->parse('run --filter spec/Acme');
            expect($result)->toBe(['command' => '/run', 'argument' => 'spec/Acme', 'tail' => '--filter spec/Acme']);
        });

        it('routes bare command words that take no argument', function () {
            expect($this->parser->parse('next')['command'])->toBe('/next');
            expect($this->parser->parse('help')['command'])->toBe('/help');
            expect($this->parser->parse('swap')['command'])->toBe('/swap');
            expect($this->parser->parse('clear')['command'])->toBe('/clear');
            expect($this->parser->parse('quit')['command'])->toBe('/quit');
            expect($this->parser->parse('exit')['command'])->toBe('/exit');
        });

        it('always routes generate, whose argument is plain English', function () {
            $result = $this->parser->parse('generate a spec for addTask');
            expect($result)->toBe(['command' => '/generate', 'argument' => 'a spec for addTask', 'tail' => 'a spec for addTask']);
        });

        it('routes a bare generate so its usage hint answers', function () {
            expect($this->parser->parse('generate')['command'])->toBe('/generate');
        });
    });

    context('lines that merely start with a command word stay conversational', function () {
        it('keeps run followed by prose for the AI', function () {
            expect($this->parser->parse('run me through the design')['command'])->toBe('run');
        });

        it('keeps next followed by prose for the AI', function () {
            expect($this->parser->parse('next time we should refactor')['command'])->toBe('next');
        });

        it('keeps help followed by prose for the AI', function () {
            expect($this->parser->parse('help me understand this project')['command'])->toBe('help');
        });

        it('keeps describe without a class-like target for the AI', function () {
            expect($this->parser->parse('describe what you mean')['command'])->toBe('describe');
        });

        it('keeps refactor without a class-like target for the AI', function () {
            expect($this->parser->parse('refactor this mess')['command'])->toBe('refactor');
        });

        it('keeps plain prose untouched', function () {
            $result = $this->parser->parse('hello world');
            expect($result)->toBe(['command' => 'hello', 'argument' => 'world', 'tail' => 'world']);
        });
    });
});
