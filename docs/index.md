# PhpSpec Documentation

A specification-oriented BDD framework for PHP 8.2+, inspired by RSpec and Jasmine.

PhpSpec lets you write expressive, readable specs using `describe`, `context`, `it`, and `expect` -- bringing the best of Ruby and JavaScript testing to PHP. Version 9.0 is a ground-up rewrite that unifies Story BDD and Spec BDD in a single tool and adds AI-native features for the modern development workflow -- including a machine-readable [`--format=agent`](agent.md) for coding agents.

## Table of Contents

0. [Introduction](introduction.md) -- Philosophy, the BDD cycle, what's new in 9.0
1. [Getting Started](getting-started.md) -- Installation, first spec, running specs
2. [Writing Specs](writing-specs.md) -- `describe`, `context`, `it`, `let`, `expect`, pending, focused
3. [Matchers](matchers.md) -- All 30+ matchers and how they work
4. [Mocking](mocking.md) -- Test doubles with `mock()`, `allow()`, and mock expectations
5. [Hooks](hooks.md) -- `beforeEach`, `afterEach`, `beforeAll`, `afterAll`
6. [Story BDD](story-bdd.md) -- Gherkin `.feature` files and step definitions
7. [Code Generation](code-generation.md) -- Specs, classes, interfaces, method stubs, step definitions
8. [Pair Programming & AI](pair.md) -- Interactive pair mode, AI assistant, refactoring
9. [Coding Agents](agent.md) -- `--format=agent`, offers, `--accept-offers`, and a CLAUDE.md snippet
10. [Guard](guard.md) -- refusing logic you never specified, and the CI check
11. [Extensions](extensions.md) -- Custom formatters, matchers, commands, listeners
12. [CLI Reference](cli.md) -- Commands, options, formatters, coverage
13. [Configuration](configuration.md) -- `phpspec.json` reference, autoloading
14. [Architecture](architecture.md) -- Internal design: loader, runner, events, results, reporting
15. [Roadmap](roadmap.md) -- Status and planned features

## Quick Example

```php
<?php

use App\Calculator;

describe(Calculator::class, function() {
    let("calculator", fn() => new Calculator());

    it("instantiates", fn() =>
        expect($this->calculator)->toBeAnInstanceOf(Calculator::class));

    context("addition", function() {
        it("adds two numbers", fn() =>
            expect($this->calculator->add(2, 3))->toBe(5));
    });
});
```

Run it:

```bash
bin/phpspec run
```

Output:

```
Once you spec, you never go back!

Calculator
    Calculator
        ✓ instantiates

        addition
            ✓ adds two numbers

1 spec
2 examples (2 passes)
```
