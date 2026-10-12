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

    private const ARGUMENT_MATCHERS = ['any', 'type', 'anInstanceOf', 'arrayIncluding', 'satisfy', 'callback', 'startWith', 'cetera'];

    private const STORY_STEPS = ['given', 'when', 'then', 'step_and', 'step_but'];

    private const STORY_HOOKS = ['beforeFeature', 'beforeScenario', 'beforeStep', 'afterStep', 'afterScenario', 'afterFeature'];

    private const BROWSER = ['visit', 'get', 'post', 'put', 'patch', 'delete'];

    private const RUNS_SUBJECT = ['toThrow'];

    private const TIMING = [
        'An expectation is judged on the line it is written, on its subject as it stands there: what the example does to an object afterwards changes neither the verdict nor what a failure reports.',
        'A failed expectation does not stop the example: every expectation is judged, in the order written, and the failure detail shows the first that did not hold.',
        'toThrow() runs its callable where the expectation is written, so what follows sees what it did.',
        'toBeCalled() and its counts are declared before the act and judge the calls made during the whole example; toHaveBeenCalled() is asked after the act and judges the calls made so far.',
        'let() values are built the first time an example reads them, once for that example; a let() no example reads never runs, one read in a beforeEach is built before the example, and one whose closure throws fails that example alone.',
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
                'support' => 'Classes under features/support load before step files, as do #[ParameterType] functions, which make a step parameter of the class they return, and #[DocStringType(name)] functions, which turn the doc string for the parameter of that name; the steps of one scenario share $this.',
            ],
            'browser' => [
                'functions' => self::functions(self::BROWSER),
                'note' => 'Every call returns a Response the response matchers accept, and attaches the exchange to the report.',
            ],
            'docs' => array_map(static fn(string $path): string => dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path), self::DOCS),
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
