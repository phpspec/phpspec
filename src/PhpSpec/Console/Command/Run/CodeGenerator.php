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

use PhpSpec\CodeGeneration\ClassGenerator;
use PhpSpec\CodeGeneration\InterfaceGenerator;
use PhpSpec\CodeGeneration\MethodStubGenerator;
use PhpSpec\CodeGeneration\SpecGenerator;
use PhpSpec\CodeGeneration\StepGenerator;
use PhpSpec\Console\Command\Pair\ScrollRegionOutput;
use PhpSpec\Results;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface as Output;

/**
 * Mediator that coordinates result scanning, source analysis, and interactive code generation.
 * Orchestrates all generation phases: step definitions, missing classes/interfaces,
 * undefined methods, and --fake mode empty-body filling.
 */
final readonly class CodeGenerator
{
    private ResultScanner $scanner;
    private SourceAnalyser $analyser;

    /**
     * @param string $srcPath relative path to the source directory
     * @param string $specPath relative path to the spec directory
     * @param bool $interactive whether to prompt for user input (false = auto-accept all)
     */
    public function __construct(
        private string $srcPath,
        private string $specPath,
        private bool $interactive = true,
        private string $specSuffix = '.spec.php',
    ) {
        $this->analyser = new SourceAnalyser();
        $this->scanner = new ResultScanner($this->analyser);
    }

    /**
     * Runs all code generation phases against the given results.
     *
     * @param Output $output the console output
     * @param Results $results the spec/feature results to scan
     * @param bool $fake whether --fake mode is enabled
     */
    public function generate(Output $output, Results $results, bool $fake): void
    {
        $this->generateStepDefinitions($output, $results);
        $this->generateMissingSpecClasses($output, $results);
        $this->generateMissingStepClasses($output, $results);
        $this->generateMissingInterfaces($output, $results);
        $this->generateMockInterfaceMethods($output, $results);
        $this->generateClassMethods($output, $results, $fake);
        if ($fake) {
            $this->fillEmptyMethods($output, $results);
        }
    }

    /**
     * Offers to generate step definition stubs for undefined Gherkin steps.
     *
     * @param Output $output the console output for prompts and confirmation messages
     * @param Results $results the results tree to scan for undefined steps
     */
    private function generateStepDefinitions(Output $output, Results $results): void
    {
        $undefinedSteps = $this->scanner->collectUndefinedSteps($results);
        if (empty($undefinedSteps)) {
            return;
        }

        foreach ($undefinedSteps as $featurePath => $steps) {
            $count = count($steps);
            $output->writeln('');
            $output->writeln(sprintf(
                '  <fg=bright-blue>%d undefined step%s in %s. Generate step definitions?</> [Y/n] ',
                $count,
                $count !== 1 ? 's' : '',
                basename($featurePath),
            ));

            if ($output instanceof ScrollRegionOutput) {
                $output->prepareForInput();
            }
            $answer = $this->ask('  > ');
            if ($output instanceof ScrollRegionOutput) {
                $output->returnToContent();
                $output->echoInput($answer ?: 'Y');
            }
            if ($answer === '' || strtolower($answer) === 'y') {
                $generator = new StepGenerator();
                $stepsFile = $generator->generate($featurePath, $steps);
                $output->writeln("  <fg=green>Step definitions generated at $stepsFile</>");
            }
        }
    }

    /**
     * Offers to generate classes for types referenced in spec examples that don't exist.
     *
     * @param Output $output the console output for prompts and confirmation messages
     * @param Results $results the results tree to scan for missing spec classes
     */
    private function generateMissingSpecClasses(Output $output, Results $results): void
    {
        $missingClasses = $this->scanner->collectMissingSpecClasses($results);
        if (empty($missingClasses)) {
            return;
        }

        $classGenerator = new ClassGenerator($this->srcPath);

        foreach ($missingClasses as $fqcn) {
            $filePath = ClassGenerator::resolveFqcn($fqcn, $this->srcPath)['filePath'];

            if (file_exists($filePath)) {
                continue;
            }

            $this->confirmAndGenerate($output, sprintf(
                '  <fg=yellow>Class <fg=white>%s</> not found. Do you want me to create it for you?</> [Y/n] ',
                $fqcn,
            ), fn() => $classGenerator->generate($fqcn), $filePath);
        }
    }

    /**
     * Offers to generate specs and classes for types referenced in steps that don't exist.
     *
     * @param Output $output the console output for prompts and confirmation messages
     * @param Results $results the results tree to scan for missing step classes
     */
    private function generateMissingStepClasses(Output $output, Results $results): void
    {
        $missingStepClasses = $this->scanner->collectMissingStepClasses($results);
        if (empty($missingStepClasses)) {
            return;
        }

        $specGenerator = new SpecGenerator($this->specPath, specSuffix: $this->specSuffix);
        $classGenerator = new ClassGenerator($this->srcPath);

        foreach ($missingStepClasses as $fqcn) {
            $specName = str_replace('\\', '/', $fqcn);

            $output->writeln('');
            $output->writeln(sprintf(
                '  <fg=yellow>Class <fg=white>%s</> not found in step. Do you want me to create a spec for it?</> [Y/n] ',
                $fqcn,
            ));

            if ($output instanceof ScrollRegionOutput) {
                $output->prepareForInput();
            }
            $answer = $this->ask('  > ');
            if ($output instanceof ScrollRegionOutput) {
                $output->returnToContent();
                $output->echoInput($answer ?: 'Y');
            }
            if ($answer === '' || strtolower($answer) === 'y') {
                $specGenerator->generate($specName);
                $output->writeln(sprintf('  <fg=green>Spec for %s created.</>', $fqcn));

                $filePath = ClassGenerator::resolveFqcn($fqcn, $this->srcPath)['filePath'];

                $this->confirmAndGenerate($output, sprintf(
                    '  <fg=yellow>Do you want me to create class <fg=white>%s</> for you?</> [Y/n] ',
                    $fqcn,
                ), fn() => $classGenerator->generate($fqcn), $filePath);
            }
        }
    }

    /**
     * Offers to generate interface files for types that mock() couldn't find.
     *
     * @param Output $output the console output for prompts and confirmation messages
     * @param Results $results the results tree to scan for missing mock types
     */
    private function generateMissingInterfaces(Output $output, Results $results): void
    {
        $missingMockTypes = $this->scanner->collectMissingMockTypes($results);

        $uniqueMissing = [];
        foreach ($missingMockTypes as $fqcn) {
            $uniqueMissing[$fqcn] = true;
        }

        if (empty($uniqueMissing)) {
            return;
        }

        $interfaceGenerator = new InterfaceGenerator($this->srcPath);

        foreach (array_keys($uniqueMissing) as $fqcn) {
            $filePath = ClassGenerator::resolveFqcn($fqcn, $this->srcPath)['filePath'];

            $this->confirmAndGenerate($output, sprintf(
                '  <fg=yellow>Do you want me to create interface <fg=white>%s</> for you?</> [Y/n] ',
                $fqcn,
            ), fn() => $interfaceGenerator->generate($fqcn), $filePath);
        }
    }

    /**
     * Offers to add method signatures to interfaces when mock doubles call undefined methods.
     *
     * @param Output $output the console output for prompts and confirmation messages
     * @param Results $results the results tree to scan for undefined mock interface methods
     */
    private function generateMockInterfaceMethods(Output $output, Results $results): void
    {
        $uniqueMockMethods = $this->uniqueByClassMethod(
            $this->scanner->collectUndefinedMockInterfaceMethods($results),
        );

        if (empty($uniqueMockMethods)) {
            return;
        }

        $methodGenerator = new MethodStubGenerator($this->srcPath);

        foreach ($uniqueMockMethods as $error) {
            $argCount = $this->analyser->extractArgumentCount($error['file'], $error['line'], $error['methodName']);
            $filePath = ClassGenerator::resolveFqcn($error['className'], $this->srcPath)['filePath'];

            $this->confirmAndGenerate($output, sprintf(
                '  <fg=yellow>Do you want me to add method <fg=white>%s()</> to interface <fg=white>%s</>?</> [Y/n] ',
                $error['methodName'],
                $error['className'],
            ), fn() => $methodGenerator->generate($error['className'], $error['methodName'], $argCount), $filePath);
        }
    }

    /**
     * Offers to generate method stubs on classes for undefined method calls.
     * In --fake mode, includes a hardcoded return value extracted from the spec's toBe() expectation.
     *
     * @param Output $output the console output for prompts and confirmation messages
     * @param Results $results the results tree to scan for undefined class methods
     * @param bool $fake whether --fake mode is enabled
     */
    private function generateClassMethods(Output $output, Results $results, bool $fake): void
    {
        $unique = $this->uniqueByClassMethod(
            $this->scanner->collectUndefinedClassMethods($results),
        );

        if (empty($unique)) {
            return;
        }

        $generator = new MethodStubGenerator($this->srcPath);

        foreach ($unique as $error) {
            $argCount = $this->analyser->extractArgumentCount($error['file'], $error['line'], $error['methodName']);
            $filePath = ClassGenerator::resolveFqcn($error['className'], $this->srcPath)['filePath'];

            $returnExpr = null;
            if ($fake) {
                $returnExpr = $this->analyser->extractExpectedReturnValue($error['file'], $error['line'], $error['methodName']);
            }

            if ($fake && $returnExpr !== null) {
                $this->confirmAndGenerate($output, sprintf(
                    '  <fg=yellow>Are you sure you want <fg=white>%s()</> to always return <fg=white>%s</>?</> [Y/n] ',
                    $error['methodName'],
                    $returnExpr,
                ), fn() => $generator->generate($error['className'], $error['methodName'], $argCount, $returnExpr), $filePath);
            } else {
                $this->confirmAndGenerate($output, sprintf(
                    '  <fg=yellow>Do you want me to create <fg=white>%s::%s()</> for you?</> [Y/n] ',
                    $error['className'],
                    $error['methodName'],
                ), fn() => $generator->generate($error['className'], $error['methodName'], $argCount), $filePath);
            }
        }
    }

    /**
     * In --fake mode, offers to fill existing empty method bodies with return expressions
     * derived from failed matcher expectations.
     *
     * @param Output $output the console output for prompts and confirmation messages
     * @param Results $results the results tree to scan for fakeable empty methods
     */
    private function fillEmptyMethods(Output $output, Results $results): void
    {
        $uniqueEmpty = $this->uniqueByClassMethod(
            $this->scanner->collectFakeableMethods($results),
        );

        if (empty($uniqueEmpty)) {
            return;
        }

        $generator = new MethodStubGenerator($this->srcPath);

        foreach ($uniqueEmpty as $candidate) {
            if (!$generator->hasEmptyMethod($candidate['className'], $candidate['methodName'])) {
                continue;
            }

            $filePath = ClassGenerator::resolveFqcn($candidate['className'], $this->srcPath)['filePath'];

            $this->confirmAndGenerate($output, sprintf(
                '  <fg=yellow>Are you sure you want <fg=white>%s()</> to always return <fg=white>%s</>?</> [Y/n] ',
                $candidate['methodName'],
                $candidate['fakeExpression'],
            ), fn() => $generator->fillEmptyMethod(
                $candidate['className'],
                $candidate['methodName'],
                $candidate['fakeExpression'],
            ), $filePath);
        }
    }

    /**
     * Deduplicates an array of error/candidate records by className::methodName.
     *
     * @param array<array<string, mixed>> $items the items to deduplicate
     * @return array<array<string, mixed>> deduplicated items
     */
    private function uniqueByClassMethod(array $items): array
    {
        $unique = [];
        foreach ($items as $item) {
            $key = $item['className'] . '::' . $item['methodName'];
            if (!isset($unique[$key])) {
                $unique[$key] = $item;
            }
        }
        return array_values($unique);
    }

    /**
     * Prompts the user with a [Y/n] question and runs the action if confirmed.
     * When a file path is provided, shows a diff of changes after generation.
     *
     * @param Output $output the console output for displaying the question and result
     * @param string $question the formatted prompt to display
     * @param callable $action the generation action to run; should return a success message string
     * @param string|null $filePath path to the file being modified, for diff display
     */
    private function confirmAndGenerate(Output $output, string $question, callable $action, ?string $filePath = null): void
    {
        $output->writeln('');
        $output->writeln($question);
        if ($output instanceof ScrollRegionOutput) {
            $output->prepareForInput();
        }
        $answer = $this->ask('  > ');
        if ($output instanceof ScrollRegionOutput) {
            $output->returnToContent();
            $output->echoInput($answer ?: 'Y');
        }
        if ($answer === '' || strtolower($answer) === 'y') {
            try {
                $oldContent = ($filePath !== null && file_exists($filePath))
                    ? file_get_contents($filePath)
                    : false;
                $oldLines = $oldContent !== false ? explode("\n", $oldContent) : null;

                $message = $action();
                $output->writeln("  <fg=green>$message</>");

                if ($filePath !== null && file_exists($filePath)) {
                    $this->showFileDiff($output, $filePath, $oldLines);
                }
            } catch (RuntimeException $e) {
                $output->writeln("  <fg=red>{$e->getMessage()}</>");
            }
        }
    }

    /**
     * Displays a file with diff markers: new lines get green `+`, existing lines show plain.
     * When there is no previous content, all lines are shown as new.
     *
     * @param Output $output the console output
     * @param string $filePath the path to the file
     * @param string[]|null $oldLines the file content before modification, or null for new files
     */
    private function showFileDiff(Output $output, string $filePath, ?array $oldLines): void
    {
        $newContent = file_get_contents($filePath);
        $newLines = $newContent !== false ? explode("\n", $newContent) : [];
        $label = $oldLines === null ? '[NEW FILE]' : '[MODIFIED]';

        $output->writeln('');
        $output->writeln("  <fg=yellow>$label</> <fg=white>$filePath</>");
        $output->writeln('');

        $oldSet = $oldLines !== null ? array_flip($oldLines) : [];

        foreach ($newLines as $i => $line) {
            $lineNum = str_pad((string) ($i + 1), 4, ' ', STR_PAD_LEFT);
            if ($oldLines === null || !isset($oldSet[$line]) || ($oldLines[$i] ?? null) !== $line) {
                $output->writeln("  <fg=green>$lineNum + </>$line");
            } else {
                $output->writeln("  <fg=gray>$lineNum   </>$line");
            }
        }
        $output->writeln('');
    }

    /**
     * Reads user input via readline (TTY) or fgets (pipe).
     *
     * @param string $prompt the prompt string to display
     * @return string the user's input, or empty string if no input
     */
    private function ask(string $prompt): string
    {
        if (!$this->interactive) {
            return '';
        }
        if (function_exists('readline') && stream_isatty(STDIN)) {
            return (string) readline($prompt);
        }
        $line = fgets(STDIN);
        return $line === false ? '' : rtrim($line, "\r\n");
    }
}
