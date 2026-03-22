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

use PhpSpec\Mock\Double;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Results;
use ReflectionClass;
use ReflectionException;

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
     * Collects undefined methods called on mock doubles whose originals are interfaces.
     * Uses the Double registry to resolve mock class names back to their original FQCNs.
     *
     * @param Results $results the results tree to scan
     * @return array<array{className: string, methodName: string, file: string, line: int}>
     */
    public function collectUndefinedMockInterfaceMethods(Results $results): array
    {
        $errors = [];
        foreach ($results->getResults() as $result) {
            if ($result instanceof ExampleResult && $result->isError()) {
                $error = $result->getError();
                if ($error !== null && preg_match('/^Call to undefined method ([A-Za-z0-9_\\\\]+)::([A-Za-z0-9_]+)\(\)$/', $error->getMessage(), $matches)) {
                    $mockClassName = $matches[1];
                    $methodName = $matches[2];

                    if (str_contains($mockClassName, '_Double_') && isset(Double::$doubleRegistry[$mockClassName])) {
                        $originalFqcn = Double::$doubleRegistry[$mockClassName];
                        try {
                            $reflection = new ReflectionClass($originalFqcn);
                            if ($reflection->isInterface()) {
                                $errors[] = [
                                    'className' => $originalFqcn,
                                    'methodName' => $methodName,
                                    'file' => $error->getFile(),
                                    'line' => $error->getLine(),
                                ];
                            }
                        } catch (ReflectionException) {
                            // Class no longer exists, skip
                        }
                    }
                }
            } elseif ($result instanceof Results) {
                $errors = array_merge($errors, $this->collectUndefinedMockInterfaceMethods($result));
            }
        }
        return $errors;
    }

    /**
     * Collects undefined methods called on real (non-mock) classes.
     *
     * @param Results $results the results tree to scan
     * @return array<array{className: string, methodName: string, file: string, line: int}>
     */
    public function collectUndefinedClassMethods(Results $results): array
    {
        $errors = [];
        foreach ($results->getResults() as $result) {
            if ($result instanceof ExampleResult && $result->isError()) {
                $error = $result->getError();
                if ($error !== null && preg_match('/^Call to undefined method ([A-Za-z0-9_\\\\]+)::([A-Za-z0-9_]+)\(\)$/', $error->getMessage(), $matches)) {
                    $className = $matches[1];
                    // Skip mock doubles — handled in collectUndefinedMockInterfaceMethods
                    if (str_contains($className, '_Double_')) {
                        continue;
                    }
                    $errors[] = [
                        'className' => $className,
                        'methodName' => $matches[2],
                        'file' => $error->getFile(),
                        'line' => $error->getLine(),
                    ];
                }
            } elseif ($result instanceof Results) {
                $errors = array_merge($errors, $this->collectUndefinedClassMethods($result));
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
     * Collects undefined step definitions grouped by feature file path.
     *
     * @param Results $results the results tree to scan
     * @return array<string, array<array{keyword: string, text: string}>> steps grouped by feature path
     */
    public function collectUndefinedSteps(Results $results): array
    {
        $byFeature = [];
        foreach ($results->getResults() as $result) {
            if ($result instanceof FeatureResult) {
                $featurePath = $result->getPath();
                foreach ($result->getResults() as $scenario) {
                    if ($scenario instanceof ScenarioResult) {
                        foreach ($scenario->getResults() as $step) {
                            if ($step instanceof StepResult && $step->isUndefined()) {
                                $title = $step->getTitle();
                                $parts = explode(' ', $title, 2);
                                $byFeature[$featurePath][] = [
                                    'keyword' => $parts[0],
                                    'text' => $parts[1] ?? $title,
                                ];
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
     * Collects FQCNs of classes that do not exist, referenced in spec examples.
     *
     * @param Results $results the results tree to scan
     * @return array<string> list of missing fully qualified class names
     */
    public function collectMissingSpecClasses(Results $results): array
    {
        $missing = [];
        foreach ($results->getResults() as $result) {
            if ($result instanceof ExampleResult && $result->isError()) {
                $error = $result->getError();
                if ($error !== null && preg_match('/^Class "([^"]+)" not found$/', $error->getMessage(), $m)) {
                    $missing[$m[1]] = true;
                }
            } elseif ($result instanceof Results) {
                foreach ($this->collectMissingSpecClasses($result) as $fqcn) {
                    $missing[$fqcn] = true;
                }
            }
        }
        return array_keys($missing);
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
                            if ($step instanceof StepResult && $step->isFailure() && $step->getError()) {
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
