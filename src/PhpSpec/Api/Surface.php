<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 * (c) Ciaran McNulty <ciaran@ciaranmcnulty.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Api;

use PhpSpec\Mock\Expectation as MockExpectation;
use PhpSpec\Report\Formatter\Agent\Schema;
use PhpSpec\Specification\Expectation;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

/**
 * @internal
 * The spec-writing surface, read off the code itself: every DSL function and
 * matcher with its real signature and the first sentence of its docblock, so
 * what a reader or a coding agent is told cannot drift from what runs. The
 * timing rules are the one part written by hand, because they cannot be
 * reflected.
 *
 * @phpstan-type Entry array{name: string, signature: string, summary: string, runs_subject?: true}
 * @phpstan-type Document array{
 *     v: int,
 *     action: string,
 *     version?: string,
 *     timing: list<string>,
 *     dsl: list<Entry>,
 *     matchers: list<Entry>,
 *     negation: Entry,
 *     mocks: array{functions: list<Entry>, expectations: list<Entry>, argument_matchers: list<Entry>},
 *     story: array{steps: list<Entry>, hooks: list<Entry>, support: string},
 *     browser: array{functions: list<Entry>, note: string},
 *     docs: array<string, string>,
 * }
 */
final readonly class Surface
{
    private const DSL = [
        'describe', 'context', 'it', 'its', 'let', 'expect',
        'beforeAll', 'beforeEach', 'afterEach', 'afterAll',
        'pending', 'skip', 'PhpSpec\attach', 'addMatcher',
        'xdescribe', 'xcontext', 'xit', 'fdescribe', 'fcontext', 'fit',
    ];

    private const MOCK_FUNCTIONS = ['mock', 'allow'];

    private const ARGUMENT_MATCHERS = ['any', 'type', 'anInstanceOf', 'arrayIncluding', 'satisfy', 'callback', 'startWith', 'cetera', 'noArgs'];

    private const STORY_STEPS = ['given', 'when', 'then', 'step_and', 'step_but'];

    private const STORY_HOOKS = ['beforeFeature', 'beforeScenario', 'beforeStep', 'afterStep', 'afterScenario', 'afterFeature'];

    private const BROWSER = ['visit', 'get', 'post', 'put', 'patch', 'delete'];

    private const RUNS_SUBJECT = ['toThrow'];

    private const TIMING = [
        'Expectations are judged at the end of the example, in the order written; the failure detail shows the first that did not hold.',
        'expect() captures its subject when called: a value the body reassigns afterwards is not seen, an object the body mutates afterwards is.',
        'toThrow() runs its callable where the expectation is written, so what follows sees what it did; only the verdict waits.',
        'Mock expectations (toBeCalled, toBeCalledTimes, toBeCalledWith) judge the calls made during the whole example.',
        'let() values are built lazily, once per example, on first access through $this.',
    ];

    private const DOCS = [
        'specs' => 'docs/writing-specs.md',
        'matchers' => 'docs/matchers.md',
        'mocking' => 'docs/mocking.md',
        'hooks' => 'docs/hooks.md',
        'story' => 'docs/story-bdd.md',
        'browser' => 'docs/browser.md',
        'agent' => 'docs/agent.md',
    ];

    public function __construct(private ?string $version = null) {}

    /**
     * @return Document
     */
    public function toArray(): array
    {
        $document = ['v' => Schema::V, 'action' => 'api'];

        if ($this->version !== null) {
            $document['version'] = $this->version;
        }

        return $document + [
            'timing' => self::TIMING,
            'dsl' => self::functions(self::DSL),
            'matchers' => self::matchers(),
            'negation' => self::entry(new ReflectionMethod(Expectation::class, 'not')),
            'mocks' => [
                'functions' => self::functions(self::MOCK_FUNCTIONS),
                'expectations' => self::methods(MockExpectation::class),
                'argument_matchers' => self::functions(self::ARGUMENT_MATCHERS),
            ],
            'story' => [
                'steps' => self::functions(self::STORY_STEPS),
                'hooks' => self::functions(self::STORY_HOOKS),
                'support' => 'Classes under features/support load before step files; the steps of one scenario share $this.',
            ],
            'browser' => [
                'functions' => self::functions(self::BROWSER),
                'note' => 'Every call returns a Response the response matchers accept, and attaches the exchange to the report.',
            ],
            'docs' => self::DOCS,
        ];
    }

    /**
     * @param list<string> $names
     * @return list<Entry>
     */
    private static function functions(array $names): array
    {
        return array_map(static fn(string $name): array => self::entry(new ReflectionFunction($name)), $names);
    }

    /**
     * @return list<Entry>
     */
    private static function matchers(): array
    {
        $matchers = [];

        foreach (self::methods(Expectation::class) as $matcher) {
            if (in_array($matcher['name'], self::RUNS_SUBJECT, true)) {
                $matcher['runs_subject'] = true;
            }

            $matchers[] = $matcher;
        }

        return $matchers;
    }

    /**
     * The public to*() methods of a class, in declaration order.
     *
     * @param class-string $class
     * @return list<Entry>
     */
    private static function methods(string $class): array
    {
        $entries = [];

        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (str_starts_with($method->getName(), 'to')) {
                $entries[] = self::entry($method);
            }
        }

        return $entries;
    }

    /**
     * @return Entry
     */
    private static function entry(ReflectionFunctionAbstract $function): array
    {
        return [
            'name' => $function->getShortName(),
            'signature' => self::signature($function),
            'summary' => self::summary($function->getDocComment()),
        ];
    }

    /**
     * The name as it is called, with its namespace when it has one, so a
     * reader knows what to import.
     */
    private static function signature(ReflectionFunctionAbstract $function): string
    {
        $parameters = array_map(self::parameter(...), $function->getParameters());
        $return = $function->getReturnType();

        return $function->getName() . '(' . implode(', ', $parameters) . ')' . ($return === null ? '' : ': ' . $return);
    }

    private static function parameter(ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();
        $text = ($type === null ? '' : $type . ' ')
            . ($parameter->isPassedByReference() ? '&' : '')
            . ($parameter->isVariadic() ? '...' : '')
            . '$' . $parameter->getName();

        if (!$parameter->isDefaultValueAvailable()) {
            return $text;
        }

        $default = $parameter->getDefaultValue();

        return $text . ' = ' . match (true) {
            $default === null => 'null',
            $default === [] => '[]',
            default => var_export($default, true),
        };
    }

    /**
     * The first paragraph of a docblock, as one sentence.
     */
    private static function summary(string|false $docComment): string
    {
        if ($docComment === false) {
            return '';
        }

        $lines = [];

        foreach (preg_split('/\R/', $docComment) ?: [] as $line) {
            $line = trim($line, " \t/*");

            if ($line === '' || str_starts_with($line, '@')) {
                if ($lines !== []) {
                    break;
                }

                continue;
            }

            $lines[] = $line;
        }

        return implode(' ', $lines);
    }
}
