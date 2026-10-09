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

namespace PhpSpec\Console\Command\Run;

use PhpSpec\CodeGeneration\StepGenerator;
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Results;

/**
 * @internal
 * Walks the Results tree and extracts structured data about errors and failures.
 * Matches error messages against known patterns to identify missing types, undefined methods,
 * undefined steps, and fakeable method candidates.
 */
final readonly class ResultScanner
{
    /**
     * @param SourceAnalyser $analyser the source analyser for resolving variable types and argument counts
     */
    public function __construct(private SourceAnalyser $analyser) {}

    /**
     * Collects FQCNs of types that could not be mocked because the class/interface does not exist.
     *
     * @param Results $results the results tree to scan
     * @return array<string> list of missing type FQCNs
     */
    public function collectMissingMockTypes(Results $results): array
    {
        $missing = [];
        foreach ($results->getResults() as $result) {
            if ($result instanceof ExampleResult && $result->isError()) {
                $error = $result->getError();
                if ($error !== null && preg_match("/Cannot create mock: class or interface '([^']+)' does not exist/", $error->getMessage(), $matches)) {
                    $missing[] = $matches[1];
                }
            } elseif ($result instanceof Results) {
                $missing = array_merge($missing, $this->collectMissingMockTypes($result));
            }
        }
        return $missing;
    }

    /**
     * Collects undefined methods called on interfaces, as a double of one
     * reports such a call: naming the interface, not the double.
     *
     * @param Results $results the results tree to scan
     * @return array<array{className: string, methodName: string, file: string, line: int}>
     */
    public function collectUndefinedMockInterfaceMethods(Results $results): array
    {
        return $this->collectUndefinedMethods($results, interface_exists(...));
    }

    /**
     * Collects undefined methods called on classes.
     *
     * @param Results $results the results tree to scan
     * @return array<array{className: string, methodName: string, file: string, line: int}>
     */
    public function collectUndefinedClassMethods(Results $results): array
    {
        return $this->collectUndefinedMethods($results, fn(string $type): bool => !interface_exists($type));
    }

    /**
     * @param callable(string): bool $onType which types' undefined methods to collect
     * @return array<array{className: string, methodName: string, file: string, line: int}>
     */
    private function collectUndefinedMethods(Results $results, callable $onType): array
    {
        $errors = [];
        foreach ($results->getResults() as $result) {
            if ($result instanceof ExampleResult && $result->isError()) {
                $error = $result->getError();
                if ($error !== null
                    && preg_match('/^Call to undefined method ([A-Za-z0-9_\\\\]+)::([A-Za-z0-9_]+)\(\)$/', $error->getMessage(), $matches)
                    && $onType($matches[1])
                ) {
                    // The call is counted where the spec made it: a double's
                    // generated code is where the error was thrown, not where
                    // the arguments are.
                    $site = $error->blame() ?? ['file' => $error->getFile(), 'line' => $error->getLine()];
                    $errors[] = [
                        'className' => $matches[1],
                        'methodName' => $matches[2],
                        'file' => $site['file'],
                        'line' => $site['line'],
                    ];
                }
            } elseif ($result instanceof Results) {
                $errors = array_merge($errors, $this->collectUndefinedMethods($result, $onType));
            }
        }

        return $errors;
    }

    /**
     * Collects methods with empty bodies that have failed expectations carrying fake expressions.
     * These are candidates for --fake mode to fill with hardcoded return values.
     *
     * @param Results $results the results tree to scan
     * @return array<array{className: string, methodName: string, fakeExpression: string, file: string, line: int}>
     */
    public function collectFakeableMethods(Results $results): array
    {
        $candidates = [];
        foreach ($results->getResults() as $result) {
            if ($result instanceof ExampleResult && $result->isFailure()) {
                foreach ($result->getResults() as $matchResult) {
                    if (!$matchResult->isFailure()) {
                        continue;
                    }
                    $fakeExpr = $matchResult->getFakeExpression();
                    if ($fakeExpr === null) {
                        continue;
                    }
                    $file = $matchResult->getFile();
                    $line = $matchResult->getLine();
                    if ($file === null || $line === null || !file_exists($file)) {
                        continue;
                    }
                    $lines = file($file);
                    if ($lines === false) {
                        continue;
                    }
                    $sourceLine = $lines[$line - 1] ?? '';

                    // Match: expect($this->prop->method(...))->... or expect($obj->method(...))->...
                    if (preg_match('/expect\(\$this->(\w+)->(\w+)\(/', $sourceLine, $parts)
                        || preg_match('/expect\(\$(\w+)->(\w+)\(/', $sourceLine, $parts)
                    ) {
                        $varName = $parts[1];
                        $methodName = $parts[2];
                        // Resolve $varName to a class name
                        $className = $this->analyser->resolveVariableClass($lines, $line - 1, $varName);
                        if ($className !== null) {
                            $candidates[] = [
                                'className' => $className,
                                'methodName' => $methodName,
                                'fakeExpression' => $fakeExpr,
                                'file' => $file,
                                'line' => $line,
                            ];
                        }
                    }
                }
            } elseif ($result instanceof Results) {
                $candidates = array_merge($candidates, $this->collectFakeableMethods($result));
            }
        }
        return $candidates;
    }

    /**
     * What each step of a feature carries under it, by the step's text: a
     * result knows a step by its title alone, and the feature file is where
     * the table or doc string under it is written.
     *
     * @return array<string, array{table?: bool, docString?: bool}>
     */
    private static function carriedByStep(string $featurePath): array
    {
        if (!is_file($featurePath)) {
            return [];
        }

        $carried = [];

        foreach (StepGenerator::parseSteps((string) file_get_contents($featurePath)) as $step) {
            $extras = array_intersect_key($step, ['table' => true, 'docString' => true]);

            if ($extras !== []) {
                $carried[$step['text']] = $extras;
            }
        }

        return $carried;
    }

    /**
     * Collects undefined step definitions grouped by feature file path.
     *
     * @param Results $results the results tree to scan
     * @return array<string, array<array{keyword: string, text: string, table?: bool, docString?: bool}>> steps grouped by feature path, each noting the table or doc string it carries
     */
    public function collectUndefinedSteps(Results $results): array
    {
        $byFeature = [];
        foreach ($results->getResults() as $result) {
            if ($result instanceof FeatureResult) {
                $featurePath = $result->getPath();
                $carried = self::carriedByStep($featurePath);
                foreach ($result->getResults() as $scenario) {
                    if ($scenario instanceof ScenarioResult) {
                        // "And"/"But" continue the last primary keyword — tracked
                        // across every step (including defined ones) so an
                        // undefined "And" after a defined "When" resolves to when.
                        $primary = 'Given';
                        foreach ($scenario->getResults() as $step) {
                            if (!$step instanceof StepResult) {
                                continue;
                            }
                            $title = $step->getTitle();
                            $parts = explode(' ', $title, 2);
                            $keyword = $parts[0];

                            if (in_array(strtolower($keyword), ['given', 'when', 'then'], true)) {
                                $primary = $keyword;
                            } else {
                                $keyword = $primary;
                            }

                            if ($step->isUndefined()) {
                                $text = $parts[1] ?? $title;
                                $byFeature[$featurePath][] = ['keyword' => $keyword, 'text' => $text] + ($carried[$text] ?? []);
                            }
                        }
                    }
                }
            } elseif ($result instanceof Results) {
                foreach ($this->collectUndefinedSteps($result) as $path => $steps) {
                    $byFeature[$path] = array_merge($byFeature[$path] ?? [], $steps);
                }
            }
        }
        return $byFeature;
    }

    /**
     * Collects the classes spec examples referenced that do not exist, each
     * keyed to the class its spec describes: the outermost describe block, or
     * the missing class itself when no block encloses the example.
     *
     * @param Results $results the results tree to scan
     * @param string|null $describes the class the enclosing spec describes
     * @return array<string, string> missing FQCN => the class the spec describes
     */
    public function collectMissingSpecClasses(Results $results, ?string $describes = null): array
    {
        $missing = [];
        foreach ($results->getResults() as $result) {
            if ($result instanceof ExampleResult && $result->isError()) {
                $fqcn = $result->getError()?->missingClass();
                if ($fqcn !== null) {
                    $missing[$fqcn] = $describes ?? $fqcn;
                }
            } elseif ($result instanceof Results) {
                $enclosing = $result instanceof ContextResult ? ($describes ?? $result->getTitle()) : $describes;
                $missing += $this->collectMissingSpecClasses($result, $enclosing);
            }
        }

        return $missing;
    }

    /**
     * Collects FQCNs of classes referenced in failed steps that do not exist.
     *
     * @param Results $results the results tree to scan
     * @return array<string> list of missing fully qualified class names
     */
    public function collectMissingStepClasses(Results $results): array
    {
        $missing = [];
        foreach ($results->getResults() as $result) {
            if ($result instanceof FeatureResult) {
                foreach ($result->getResults() as $scenario) {
                    if ($scenario instanceof ScenarioResult) {
                        foreach ($scenario->getResults() as $step) {
                            if ($step instanceof StepResult && ($step->isFailure() || $step->isError()) && $step->getError()) {
                                $msg = $step->getError()->getMessage();
                                if (preg_match('/^Class "([^"]+)" not found$/', $msg, $m)) {
                                    $missing[$m[1]] = true;
                                }
                            }
                        }
                    }
                }
            } elseif ($result instanceof Results) {
                foreach ($this->collectMissingStepClasses($result) as $fqcn) {
                    $missing[$fqcn] = true;
                }
            }
        }
        return array_keys($missing);
    }
}
