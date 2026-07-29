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

namespace PhpSpec\Report\Formatter\Pretty;

use PhpSpec\CodeGeneration\SurroundingCode;
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\StepResult;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 * Static view methods for the Pretty formatter, replacing the .view.php templates.
 */
final class PrettyViews
{
    public static function specification(OutputInterface $output, SpecificationResult $specification, bool $verbose = false): void
    {
        $output->write(PHP_EOL);
        $output->write($specification->getTitle());
        foreach ($specification->getResults() as $exampleResult) {
            if ($exampleResult instanceof ContextResult) {
                self::context($output, $exampleResult, 2, $verbose);
            } elseif ($exampleResult instanceof ExampleResult) {
                self::example($output, $exampleResult, 2, $verbose);
            }
        }
    }

    public static function specificationErrors(OutputInterface $output, SpecificationResult $specification): void
    {
        foreach ($specification->getResults() as $exampleResult) {
            if ($exampleResult instanceof ContextResult && !$exampleResult->isError()) {
                self::contextErrors($output, $exampleResult, $specification->getTitle() . ' > ' . $exampleResult->getTitle());
            } elseif ($exampleResult instanceof ContextResult && $exampleResult->isError()) {
                $error = $exampleResult->getError();
                $output->write(PHP_EOL . '  <fg=red>• ' . $specification->getTitle() . ' > ' . $exampleResult->getTitle() . '</>' . PHP_EOL);
                $output->write(PHP_EOL);
                $output->write('  <fg=red>' . '</>' . $error->getType() . ': ' . $error->getMessage() . "\n\n");
                self::surroundingCode($output, $error->getSurroundingCode(), $error->getLine());
                $output->write(PHP_EOL . '  at ' . $error->getFile() . ':' . $error->getLine() . PHP_EOL);
            } elseif ($exampleResult instanceof ExampleResult) {
                self::exampleErrors($output, $exampleResult, $specification->getTitle());
            }
        }
    }

    public static function context(OutputInterface $output, ContextResult $context, int $indentation, bool $verbose = false): void
    {
        $output->write(PHP_EOL . str_repeat(' ', $indentation));
        $output->write($context->getTitle());
        foreach ($context->getResults() as $example) {
            if ($example instanceof ContextResult) {
                self::context($output, $example, $indentation + 2, $verbose);
            } elseif ($example instanceof ExampleResult) {
                self::example($output, $example, $indentation + 2, $verbose);
            }
        }
    }

    public static function contextErrors(OutputInterface $output, ContextResult $context, string $specification): void
    {
        foreach ($context->getResults() as $example) {
            if ($example instanceof ContextResult) {
                self::contextErrors($output, $example, $specification . ' > ' . $example->getTitle());
            } elseif ($example instanceof ExampleResult) {
                self::exampleErrors($output, $example, $specification);
            }
        }
    }

    public static function example(OutputInterface $output, ExampleResult $example, int $indentation, bool $verbose = false): void
    {
        $output->write(PHP_EOL);
        if ($example->isPending()) {
            $output->write(str_repeat(' ', $indentation));
            $output->write('<fg=yellow>-</> <fg=gray>' . $example->getTitle() . ' (pending)</>');
        } elseif ($example->isSkipped()) {
            $output->write(str_repeat(' ', $indentation));
            $output->write('<fg=cyan>-</> <fg=gray>' . $example->getTitle() . ' (skipped)</>');
        } elseif ($example->isError()) {
            $output->write(str_repeat(' ', $indentation));
            $output->write('<fg=red>✘</> <fg=gray>' . $example->getTitle() . '</>');
        } elseif ($example->isFailure()) {
            $output->write(str_repeat(' ', $indentation));
            $output->write('<fg=red>✘</> <fg=gray>' . $example->getTitle() . '</>');
        } else {
            $output->write(str_repeat(' ', $indentation));
            $output->write('<info>' . '√ ' . '</info><fg=gray>' . $example->getTitle() . '</>');
            if ($verbose && $example->getDuration() > 0) {
                $output->write(' <fg=gray>(' . number_format($example->getDuration() * 1000, 1) . 'ms)</>');
            }
        }
        if ($example->hasWarnings()) {
            foreach ($example->getWarnings() as $warning) {
                $output->write(PHP_EOL . str_repeat(' ', $indentation) . '  <fg=yellow>⚠️ ' . $warning['message'] . ' — at ' . basename($warning['file']) . ':' . $warning['line'] . '</>');
            }
        }
        if ($example->hasDeprecations()) {
            foreach ($example->getDeprecations() as $deprecation) {
                $output->write(PHP_EOL . str_repeat(' ', $indentation) . '  <fg=yellow>⛔ ' . $deprecation['message'] . ' — at ' . basename($deprecation['file']) . ':' . $deprecation['line'] . '</>');
            }
        }
        if ($example->hasNotices()) {
            foreach ($example->getNotices() as $notice) {
                $output->write(PHP_EOL . str_repeat(' ', $indentation) . '  <fg=yellow>ℹ ' . $notice['message'] . ' — at ' . basename($notice['file']) . ':' . $notice['line'] . '</>');
            }
        }
    }

    public static function exampleErrors(OutputInterface $output, ExampleResult $example, string $specification): void
    {
        if ($example->isError()) {
            $error = $example->getError();
            if ($error !== null) {
                $output->write(PHP_EOL . '  <fg=red>• ' . $specification . ' > ' . $example->getTitle() . '</>' . PHP_EOL);
                $output->write(PHP_EOL . '  <fg=red>' . '</>' . 'Error: ' . $error->getMessage() . PHP_EOL . PHP_EOL);
                self::surroundingCode($output, $error->getSurroundingCode(), $error->getLine());
                $output->write(PHP_EOL . '  at ' . $error->getFile() . ':' . $error->getLine() . PHP_EOL);
                $trace = $error->getFilteredTrace();
                if (!empty($trace)) {
                    foreach (array_slice($trace, 0, 5) as $frame) {
                        $output->write('     ' . ($frame['file'] ?? '?') . ':' . ($frame['line'] ?? '?') . PHP_EOL);
                    }
                }
            }
        } elseif ($example->isFailure()) {
            $output->write(str_repeat(' ', 2));
            $output->write(PHP_EOL . '  <fg=red>• ' . $specification . ' > ' . $example->getTitle() . '</>' . PHP_EOL);
            $output->write(PHP_EOL);
            /** @var MatchResult $matchResult */
            foreach ($example->getResults() as $matchResult) {
                // Passing expectations carry the Nothing sentinel, not a
                // message; only the failure is the story.
                if (!$matchResult->isFailure()) {
                    continue;
                }

                $output->write(str_repeat(' ', 2));
                self::matchResult($output, $matchResult);
            }
        }
        if ($example->hasWarnings()) {
            foreach ($example->getWarnings() as $warning) {
                $output->write(PHP_EOL . '  <fg=yellow>⚠️ ' . $specification . ' > ' . $example->getTitle() . '</>' . PHP_EOL);
                $output->write(PHP_EOL . '  Warning: ' . $warning['message'] . PHP_EOL . PHP_EOL);
                self::surroundingCode($output, (new SurroundingCode($warning['file'], $warning['line']))->toArray(), $warning['line']);
                $output->write(PHP_EOL . '  at ' . $warning['file'] . ':' . $warning['line'] . PHP_EOL);
            }
        }
        if ($example->hasDeprecations()) {
            foreach ($example->getDeprecations() as $deprecation) {
                $output->write(PHP_EOL . '  <fg=yellow>⚠️ ' . $specification . ' > ' . $example->getTitle() . '</>' . PHP_EOL);
                $output->write(PHP_EOL . '  Deprecated: ' . $deprecation['message'] . PHP_EOL . PHP_EOL);
                self::surroundingCode($output, (new SurroundingCode($deprecation['file'], $deprecation['line']))->toArray(), $deprecation['line']);
                $output->write(PHP_EOL . '  at ' . $deprecation['file'] . ':' . $deprecation['line'] . PHP_EOL);
            }
        }
        if ($example->hasNotices()) {
            foreach ($example->getNotices() as $notice) {
                $output->write(PHP_EOL . '  <fg=yellow>⚠️ ' . $specification . ' > ' . $example->getTitle() . '</>' . PHP_EOL);
                $output->write(PHP_EOL . '  Notice: ' . $notice['message'] . PHP_EOL . PHP_EOL);
                self::surroundingCode($output, (new SurroundingCode($notice['file'], $notice['line']))->toArray(), $notice['line']);
                $output->write(PHP_EOL . '  at ' . $notice['file'] . ':' . $notice['line'] . PHP_EOL);
            }
        }
    }

    public static function feature(OutputInterface $output, FeatureResult $feature): void
    {
        $output->write(PHP_EOL);
        $output->write('<options=bold>Feature: ' . $feature->getTitle() . '</>');
        foreach ($feature->getResults() as $scenarioResult) {
            if ($scenarioResult instanceof ScenarioResult) {
                self::scenario($output, $scenarioResult);
            }
        }
    }

    public static function featureErrors(OutputInterface $output, FeatureResult $feature): void
    {
        foreach ($feature->getResults() as $scenario) {
            if (!$scenario instanceof ScenarioResult) {
                continue;
            }
            foreach ($scenario->getResults() as $step) {
                if (!$step instanceof StepResult) {
                    continue;
                }
                if ($step->isFailure() && $step->getError() !== null) {
                    $output->write(PHP_EOL . '  <fg=red>• ' . $feature->getTitle() . ' > ' . $scenario->getTitle() . ' > ' . $step->getTitle() . '</>' . PHP_EOL);
                    $output->write(PHP_EOL . '  ' . $step->getError()->getMessage() . PHP_EOL);
                }
            }
        }
    }

    public static function scenario(OutputInterface $output, ScenarioResult $scenario): void
    {
        $output->write(PHP_EOL . '  ' . $scenario->getTitle());
        foreach ($scenario->getResults() as $step) {
            if ($step instanceof StepResult) {
                self::step($output, $step);
            }
        }
    }

    public static function step(OutputInterface $output, StepResult $step): void
    {
        $output->write(PHP_EOL);
        if ($step->isPassed()) {
            $output->write('    <fg=green>✓ ' . $step->getTitle() . '</>');
        } elseif ($step->isFailure()) {
            $output->write('    <fg=red>✗ ' . $step->getTitle() . '</>');
        } elseif ($step->isPending()) {
            $output->write('    <fg=yellow>○ ' . $step->getTitle() . '</>');
        } elseif ($step->isUndefined()) {
            $output->write('    <fg=bright-blue>? ' . $step->getTitle() . '</>');
        } elseif ($step->isSkipped()) {
            $output->write('    <fg=cyan>- ' . $step->getTitle() . '</>');
        }
    }

    public static function matchResult(OutputInterface $output, MatchResult $matchResult): void
    {
        $output->write('Failure: ' . $matchResult->getMessage() . PHP_EOL . PHP_EOL);

        $expected = $matchResult->getExpected();
        $actual = $matchResult->getActual();
        if ($expected !== null || $actual !== null) {
            $format = static function (mixed $value): string {
                if (is_string($value)) {
                    return '"' . $value . '"';
                }
                if (is_bool($value)) {
                    return $value ? 'true' : 'false';
                }
                if (is_null($value)) {
                    return 'null';
                }
                if (is_array($value)) {
                    return var_export($value, true);
                }
                if (is_object($value)) {
                    return get_class($value) . '#' . spl_object_id($value);
                }
                return (string) $value;
            };
            $output->write('  <fg=red>  expected: ' . $format($actual) . '</>' . PHP_EOL);
            $output->write('  <fg=green>       got: ' . $format($expected) . '</>' . PHP_EOL . PHP_EOL);
        }

        $line = $matchResult->getLine();
        if ($line !== null) {
            self::surroundingCode($output, $matchResult->getCode(), $line);
            $output->write(PHP_EOL . '  at ' . $matchResult->getFile() . ':' . $line . PHP_EOL);
        }
    }

    /**
     * @param array<int, string> $surroundingCode
     */
    public static function surroundingCode(OutputInterface $output, array $surroundingCode, int $errorLine): void
    {
        end($surroundingCode);
        $decimalPlace = strlen((string) key($surroundingCode));
        reset($surroundingCode);

        foreach ($surroundingCode as $line => $code) {
            $indent = strlen((string) $line) < $decimalPlace ? ' ' : '';

            if ($errorLine === $line) {
                $output->write(" <fg=red>></> {$indent}<options=bold>$line</>  <fg=gray>|</> <fg=red>$code</>");
            } else {
                $output->write("   {$indent}<fg=gray>$line  |</> $code");
            }
        }
    }

    /**
     * @param array<string, int> $counts
     */
    public static function counts(OutputInterface $output, array $counts, float $duration = 0): void
    {
        if (!empty($counts['features'])) {
            $output->write($counts['features'] . ' feature' . ($counts['features'] != 1 ? 's' : '') . ', ');
            $output->write($counts['scenarios'] . ' scenario' . ($counts['scenarios'] != 1 ? 's' : '') . ', ');
            $output->write($counts['steps'] . ' step' . ($counts['steps'] != 1 ? 's' : '') . ' (');
            $parts = [];
            if ($counts['stepPasses']) {
                $parts[] = '<fg=green>' . $counts['stepPasses'] . ' passed</>';
            }
            if ($counts['stepFailures']) {
                $parts[] = '<fg=red>' . $counts['stepFailures'] . ' failed</>';
            }
            if ($counts['pending']) {
                $parts[] = '<fg=yellow>' . $counts['pending'] . ' pending</>';
            }
            if ($counts['undefined']) {
                $parts[] = '<fg=bright-blue>' . $counts['undefined'] . ' undefined</>';
            }
            if ($counts['skipped']) {
                $parts[] = '<fg=cyan>' . $counts['skipped'] . ' skipped</>';
            }
            $output->write(implode(', ', $parts));
            $output->write(')' . PHP_EOL);
        } elseif (isset($counts['specs'])) {
            $output->write($counts['specs'] . ' spec' . ($counts['specs'] != 1 ? 's' : '') . PHP_EOL);
        }
        if (isset($counts['examples']) && $counts['examples'] > 0) {
            $exParts = [];
            if ($counts['passes']) {
                $exParts[] = '<fg=green>' . $counts['passes'] . ' passes</>';
            }
            if ($counts['failures']) {
                $exParts[] = '<fg=red>' . $counts['failures'] . ' failures</>';
            }
            if ($counts['errors']) {
                $exParts[] = '<fg=red>' . $counts['errors'] . ' errors</>';
            }
            if ($counts['pending']) {
                $exParts[] = '<fg=yellow>' . $counts['pending'] . ' pending</>';
            }
            if ($counts['exampleSkipped']) {
                $exParts[] = '<fg=cyan>' . $counts['exampleSkipped'] . ' skipped</>';
            }
            if ($counts['warnings']) {
                $exParts[] = '<fg=yellow>' . $counts['warnings'] . ' warning' . ($counts['warnings'] != 1 ? 's' : '') . '</>';
            }
            if ($counts['deprecations']) {
                $exParts[] = '<fg=yellow>' . $counts['deprecations'] . ' deprecation' . ($counts['deprecations'] != 1 ? 's' : '') . '</>';
            }
            if ($counts['notices']) {
                $exParts[] = '<fg=yellow>' . $counts['notices'] . ' notice' . ($counts['notices'] != 1 ? 's' : '') . '</>';
            }
            $output->write($counts['examples'] . ' example' . ($counts['examples'] != 1 ? 's' : '') . ' (' . implode(', ', $exParts) . ')' . PHP_EOL);
        }
        if ($duration > 0) {
            $output->write(sprintf('Finished in %.4f seconds' . PHP_EOL, $duration));
        }
    }
}
