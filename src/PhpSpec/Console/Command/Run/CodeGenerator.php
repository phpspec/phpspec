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
use PhpSpec\Console\Command\Pair\Chooser;
use PhpSpec\Console\Command\Pair\ScrollRegionOutput;
use PhpSpec\Results;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface as Output;

/**
 * @internal
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
     * @param string $specSuffix file suffix for spec files
     * @param string $psr4Prefix PSR-4 namespace prefix mapped to $srcPath
     * @param Chooser|null $chooser when given, questions are presented through this
     *                              numbered chooser (pair mode) instead of a plain [Y/n] prompt
     */
    public function __construct(
        private string $srcPath,
        private string $specPath,
        private bool $interactive = true,
        private string $specSuffix = '.spec.php',
        private string $psr4Prefix = '',
        private ?Chooser $chooser = null,
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
        $this->apply($output, $this->scan($results), $fake);
    }

    /**
     * Extracts every code-generation opportunity from a run's results, without
     * prompting or writing anything. Runs in the process that executed the
     * specs (it relies on that run's in-memory state, e.g. the mock registry).
     *
     * @param Results $results the spec/feature results to scan
     * @return GenerationCandidates the opportunities found
     */
    public function scan(Results $results): GenerationCandidates
    {
        return new GenerationCandidates(
            $this->scanner->collectUndefinedSteps($results),
            $this->scanner->collectMissingSpecClasses($results),
            $this->scanner->collectMissingStepClasses($results),
            $this->scanner->collectMissingMockTypes($results),
            $this->scanner->collectUndefinedMockInterfaceMethods($results),
            $this->scanner->collectUndefinedClassMethods($results),
            $this->scanner->collectFakeableMethods($results),
        );
    }

    /**
     * Interactively generates the code for a set of candidates, prompting for
     * each. May run in a different process than the one that produced them.
     *
     * @param Output $output the console output for prompts and confirmation messages
     * @param GenerationCandidates $candidates the opportunities to offer
     * @param bool $fake whether --fake mode is enabled
     */
    public function apply(Output $output, GenerationCandidates $candidates, bool $fake): void
    {
        $this->generateStepDefinitions($output, $candidates->undefinedSteps);
        $this->generateMissingSpecClasses($output, $candidates->missingSpecClasses);
        $this->generateMissingStepClasses($output, $candidates->missingStepClasses);
        $this->generateMissingInterfaces($output, $candidates->missingMockTypes);
        $this->generateMockInterfaceMethods($output, $candidates->undefinedMockInterfaceMethods);
        $this->generateClassMethods($output, $candidates->undefinedClassMethods, $fake);
        if ($fake) {
            $this->fillEmptyMethods($output, $candidates->fakeableMethods);
        }
    }

    /**
     * Offers to generate step definition stubs for undefined Gherkin steps.
     *
     * @param Output $output the console output for prompts and confirmation messages
     * @param array<string, array<array{keyword: string, text: string}>> $undefinedSteps undefined steps grouped by feature file path
     */
    private function generateStepDefinitions(Output $output, array $undefinedSteps): void
    {
        if (empty($undefinedSteps)) {
            return;
        }

        foreach ($undefinedSteps as $featurePath => $steps) {
            $count = count($steps);
            $question = sprintf(
                '  <fg=bright-blue>%d undefined step%s in %s. Generate step definitions?</>',
                $count,
                $count !== 1 ? 's' : '',
                basename($featurePath),
            );

            if ($this->confirm($output, $question, 'generate-steps', 'generate step definitions')) {
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
     * @param array<string> $missingClasses FQCNs referenced in specs that do not exist
     */
    private function generateMissingSpecClasses(Output $output, array $missingClasses): void
    {
        if (empty($missingClasses)) {
            return;
        }

        $classGenerator = new ClassGenerator($this->srcPath);

        foreach ($missingClasses as $fqcn) {
            $filePath = ClassGenerator::resolveFqcn($fqcn, $this->srcPath, $this->psr4Prefix)['filePath'];

            if (file_exists($filePath)) {
                continue;
            }

            $this->confirmAndGenerate($output, sprintf(
                '  <fg=yellow>Class <fg=white>%s</> not found. Do you want me to create it for you?</>',
                $fqcn,
            ), 'create-class', 'create classes', fn() => $classGenerator->generate($fqcn), $filePath);
        }
    }

    /**
     * Offers to generate specs and classes for types referenced in steps that don't exist.
     *
     * @param Output $output the console output for prompts and confirmation messages
     * @param array<string> $missingStepClasses FQCNs referenced in steps that do not exist
     */
    private function generateMissingStepClasses(Output $output, array $missingStepClasses): void
    {
        if (empty($missingStepClasses)) {
            return;
        }

        $specGenerator = new SpecGenerator($this->specPath, specSuffix: $this->specSuffix);
        $classGenerator = new ClassGenerator($this->srcPath);

        foreach ($missingStepClasses as $fqcn) {
            $specName = str_replace('\\', '/', $fqcn);

            $question = sprintf(
                '  <fg=yellow>Class <fg=white>%s</> not found in step. Do you want me to create a spec for it?</>',
                $fqcn,
            );

            if ($this->confirm($output, $question, 'create-spec', 'create specs')) {
                $specGenerator->generate($specName);
                $output->writeln(sprintf('  <fg=green>Spec for %s created.</>', $fqcn));

                $filePath = ClassGenerator::resolveFqcn($fqcn, $this->srcPath, $this->psr4Prefix)['filePath'];

                $this->confirmAndGenerate($output, sprintf(
                    '  <fg=yellow>Do you want me to create class <fg=white>%s</> for you?</>',
                    $fqcn,
                ), 'create-class', 'create classes', fn() => $classGenerator->generate($fqcn), $filePath);
            }
        }
    }

    /**
     * Offers to generate interface files for types that mock() couldn't find.
     *
     * @param Output $output the console output for prompts and confirmation messages
     * @param array<string> $missingMockTypes FQCNs that could not be mocked because they do not exist
     */
    private function generateMissingInterfaces(Output $output, array $missingMockTypes): void
    {
        $uniqueMissing = [];
        foreach ($missingMockTypes as $fqcn) {
            $uniqueMissing[$fqcn] = true;
        }

        if (empty($uniqueMissing)) {
            return;
        }

        $interfaceGenerator = new InterfaceGenerator($this->srcPath);

        foreach (array_keys($uniqueMissing) as $fqcn) {
            $filePath = ClassGenerator::resolveFqcn($fqcn, $this->srcPath, $this->psr4Prefix)['filePath'];

            $this->confirmAndGenerate($output, sprintf(
                '  <fg=yellow>Do you want me to create interface <fg=white>%s</> for you?</>',
                $fqcn,
            ), 'create-interface', 'create interfaces', fn() => $interfaceGenerator->generate($fqcn), $filePath);
        }
    }

    /**
     * Offers to add method signatures to interfaces when mock doubles call undefined methods.
     *
     * @param Output $output the console output for prompts and confirmation messages
     * @param array<array{className: string, methodName: string, file: string, line: int}> $mockMethods undefined interface-mock methods
     */
    private function generateMockInterfaceMethods(Output $output, array $mockMethods): void
    {
        $uniqueMockMethods = $this->uniqueByClassMethod($mockMethods);

        if (empty($uniqueMockMethods)) {
            return;
        }

        $methodGenerator = new MethodStubGenerator($this->srcPath);

        foreach ($uniqueMockMethods as $error) {
            $argCount = $this->analyser->extractArgumentCount($error['file'], $error['line'], $error['methodName']);
            $filePath = ClassGenerator::resolveFqcn($error['className'], $this->srcPath, $this->psr4Prefix)['filePath'];

            $this->confirmAndGenerate($output, sprintf(
                '  <fg=yellow>Do you want me to add method <fg=white>%s()</> to interface <fg=white>%s</>?</>',
                $error['methodName'],
                $error['className'],
            ), 'add-interface-method', 'add interface methods', fn() => $methodGenerator->generate($error['className'], $error['methodName'], $argCount), $filePath);
        }
    }

    /**
     * Offers to generate method stubs on classes for undefined method calls.
     * In --fake mode, includes a hardcoded return value extracted from the spec's toBe() expectation.
     *
     * @param Output $output the console output for prompts and confirmation messages
     * @param array<array{className: string, methodName: string, file: string, line: int}> $classMethods undefined real-class methods
     * @param bool $fake whether --fake mode is enabled
     */
    private function generateClassMethods(Output $output, array $classMethods, bool $fake): void
    {
        $unique = $this->uniqueByClassMethod($classMethods);

        if (empty($unique)) {
            return;
        }

        $generator = new MethodStubGenerator($this->srcPath);

        foreach ($unique as $error) {
            $argCount = $this->analyser->extractArgumentCount($error['file'], $error['line'], $error['methodName']);
            $filePath = ClassGenerator::resolveFqcn($error['className'], $this->srcPath, $this->psr4Prefix)['filePath'];

            $returnExpr = null;
            if ($fake) {
                $returnExpr = $this->analyser->extractExpectedReturnValue($error['file'], $error['line'], $error['methodName']);
            }

            if ($fake && $returnExpr !== null) {
                $this->confirmAndGenerate($output, sprintf(
                    '  <fg=yellow>Are you sure you want <fg=white>%s()</> to always return <fg=white>%s</>?</>',
                    $error['methodName'],
                    $returnExpr,
                ), 'confirm-fake-return', 'set fake return values', fn() => $generator->generate($error['className'], $error['methodName'], $argCount, $returnExpr), $filePath);
            } else {
                $this->confirmAndGenerate($output, sprintf(
                    '  <fg=yellow>Do you want me to create <fg=white>%s::%s()</> for you?</>',
                    $error['className'],
                    $error['methodName'],
                ), 'create-method', 'create methods', fn() => $generator->generate($error['className'], $error['methodName'], $argCount), $filePath);
            }
        }
    }

    /**
     * In --fake mode, offers to fill existing empty method bodies with return expressions
     * derived from failed matcher expectations.
     *
     * @param Output $output the console output for prompts and confirmation messages
     * @param array<array{className: string, methodName: string, fakeExpression: string, file: string, line: int}> $fakeableMethods empty methods that --fake could fill
     */
    private function fillEmptyMethods(Output $output, array $fakeableMethods): void
    {
        $uniqueEmpty = $this->uniqueByClassMethod($fakeableMethods);

        if (empty($uniqueEmpty)) {
            return;
        }

        $generator = new MethodStubGenerator($this->srcPath);

        foreach ($uniqueEmpty as $candidate) {
            if (!$generator->hasEmptyMethod($candidate['className'], $candidate['methodName'])) {
                continue;
            }

            $filePath = ClassGenerator::resolveFqcn($candidate['className'], $this->srcPath, $this->psr4Prefix)['filePath'];

            $this->confirmAndGenerate($output, sprintf(
                '  <fg=yellow>Are you sure you want <fg=white>%s()</> to always return <fg=white>%s</>?</>',
                $candidate['methodName'],
                $candidate['fakeExpression'],
            ), 'confirm-fake-return', 'set fake return values', fn() => $generator->fillEmptyMethod(
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
     * Asks a question and runs the action if confirmed.
     * When a file path is provided, shows a diff of changes after generation.
     *
     * @param Output $output the console output for displaying the question and result
     * @param string $question the question to display, without a "[Y/n]" suffix
     * @param string $kind stable identifier grouping this question for chooser "always" memory
     * @param string $action verb phrase completing "always ..." in the chooser
     * @param callable $generate the generation action to run; should return a success message string
     * @param string|null $filePath path to the file being modified, for diff display
     */
    private function confirmAndGenerate(Output $output, string $question, string $kind, string $action, callable $generate, ?string $filePath = null): void
    {
        if (!$this->confirm($output, $question, $kind, $action)) {
            return;
        }

        try {
            $oldContent = ($filePath !== null && file_exists($filePath))
                ? file_get_contents($filePath)
                : false;
            $oldLines = $oldContent !== false ? explode("\n", $oldContent) : null;

            $message = $generate();
            $output->writeln("  <fg=green>$message</>");

            if ($filePath !== null && file_exists($filePath)) {
                $this->showFileDiff($output, $filePath, $oldLines);
            }
        } catch (RuntimeException $e) {
            $output->writeln("  <fg=red>{$e->getMessage()}</>");
        }
    }

    /**
     * Asks a yes/no question, through the pair-mode chooser when one is
     * available, falling back to a plain [Y/n] prompt otherwise.
     *
     * @param Output $output the console output for displaying the question
     * @param string $question the question to display, without a "[Y/n]" suffix
     * @param string $kind stable identifier grouping this question for chooser "always" memory
     * @param string $action verb phrase completing "always ..." in the chooser
     * @return bool true when the user accepted
     */
    private function confirm(Output $output, string $question, string $kind, string $action): bool
    {
        if ($this->chooser !== null) {
            return $this->chooser->choose($question, $kind, $action);
        }

        $output->writeln('');
        $output->writeln("$question [Y/n] ");
        if ($output instanceof ScrollRegionOutput) {
            $output->prepareForInput();
        }
        $answer = $this->ask('  > ');
        if ($output instanceof ScrollRegionOutput) {
            $output->returnToContent();
            $output->echoInput($answer ?: 'Y');
        }

        return $answer === '' || strtolower($answer) === 'y';
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
