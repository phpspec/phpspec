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
use PhpSpec\CodeGeneration\ClassLocation;
use PhpSpec\CodeGeneration\InterfaceGenerator;
use PhpSpec\CodeGeneration\MethodStubGenerator;
use PhpSpec\CodeGeneration\SourceLayout;
use PhpSpec\CodeGeneration\SpecGenerator;
use PhpSpec\CodeGeneration\StepGenerator;
use PhpSpec\CodeGeneration\StepsHome;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Pair\Chooser;
use PhpSpec\Console\Command\Pair\ScrollRegionOutput;
use PhpSpec\Console\Command\Refactor\Diff;
use PhpSpec\Console\Prompt;
use PhpSpec\Filesystem;
use PhpSpec\Offers\Offer;
use PhpSpec\ProjectRoot;
use PhpSpec\RealFilesystem;
use PhpSpec\Results;
use PhpSpec\StoryBDD\StepVocabulary;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface as Output;

/**
 * @internal
 * Mediator that coordinates result scanning, source analysis, and interactive code generation.
 * Orchestrates all generation phases: step definitions, missing classes/interfaces,
 * undefined methods, and --fake mode empty-body filling.
 *
 * @phpstan-type Applied array{id: string, action: string, target: string, file: string, applied: bool, reason?: string}
 */
final readonly class CodeGenerator
{
    private ResultScanner $scanner;
    private SourceAnalyser $analyser;
    private Filesystem $filesystem;

    /**
     * @param SourceLayout $layout where a class's file lives
     * @param string $specPath relative path to the spec directory
     * @param Generation $generation how an offer is answered when nobody is asked
     * @param string $specSuffix file suffix for spec files
     * @param Chooser|null $chooser when given, questions are presented through this
     *                              numbered chooser (pair mode) instead of a plain [Y/n] prompt
     * @param StepsHome $steps where the project keeps its step definitions
     * @param Prompt $prompt reads the person's answers
     */
    public function __construct(
        private SourceLayout $layout,
        private string $specPath,
        private Generation $generation = Generation::Asks,
        private string $specSuffix = '.spec.php',
        private ?Chooser $chooser = null,
        private StepsHome $steps = new StepsHome(new Configuration()),
        private Prompt $prompt = new Prompt(),
    ) {
        $this->analyser = new SourceAnalyser();
        $this->scanner = new ResultScanner($this->analyser);
        $this->filesystem = new RealFilesystem();
    }

    /**
     * Runs all code generation phases against the given results.
     *
     * @param Output $output the console output
     * @param Results $results the spec/feature results to scan
     * @param bool $fake whether --fake mode is enabled
     * @return list<Applied> what was written, and what could not be, each under the id its offer carries
     */
    public function generate(Output $output, Results $results, bool $fake): array
    {
        return $this->apply($output, $this->scan($results), $fake);
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
            array_values(array_merge(...array_values($this->scanner->collectUndefinedSteps($results)))),
            $this->scanner->collectMissingSpecClasses($results),
            $this->scanner->collectMissingStepClasses($results),
            $this->scanner->collectMissingMockTypes($results),
            $this->scanner->collectUndefinedMockInterfaceMethods($results),
            $this->scanner->collectUndefinedClassMethods($results),
            $this->scanner->collectFakeableMethods($results),
            $this->steps->defaultFile(),
        );
    }

    /**
     * Interactively generates the code for a set of candidates, prompting for
     * each. May run in a different process than the one that produced them.
     *
     * @param Output $output the console output for prompts and confirmation messages
     * @param GenerationCandidates $candidates the opportunities to offer
     * @param bool $fake whether --fake mode is enabled
     * @return list<Applied> what was written, and what could not be, each under the id its offer carries
     */
    public function apply(Output $output, GenerationCandidates $candidates, bool $fake): array
    {
        return [
            ...$this->generateStepDefinitions($output, $candidates),
            ...$this->generateMissingSpecClasses($output, $candidates->missingSpecClasses),
            ...$this->generateMissingStepClasses($output, $candidates->missingStepClasses),
            ...$this->generateMissingInterfaces($output, $candidates->missingMockTypes),
            ...$this->generateMockInterfaceMethods($output, $candidates->undefinedMockInterfaceMethods),
            ...$this->generateClassMethods($output, $candidates->undefinedClassMethods, $fake),
            ...($fake ? $this->fillEmptyMethods($output, $candidates->fakeableMethods) : []),
        ];
    }

    /**
     * Offers the undefined steps of the run, all of them, to one steps file:
     * steps.php when there is none yet, or the one the person picks.
     *
     * @return list<Applied>
     */
    private function generateStepDefinitions(Output $output, GenerationCandidates $candidates): array
    {
        if ($candidates->undefinedSteps === []) {
            return [];
        }

        $file = $this->stepsDestination($output, $candidates->stepsFile);
        if ($file === null) {
            return [];
        }

        (new StepGenerator($this->filesystem))->generate(
            $this->steps->absolute($file),
            $candidates->undefinedSteps,
            array_keys((new StepVocabulary($this->filesystem))->definedTitles(...$this->steps->roots())),
        );
        $output->writeln(sprintf('  <fg=green>Step definitions generated at %s</>', $file));

        return [self::applied('create_steps', $file, $file)];
    }

    /**
     * The steps file the undefined steps go to, or null when they go nowhere.
     * A yes or no for the default file when there is no steps file to choose
     * from, or nobody to choose (accepted offers, the pair chooser, no one
     * there); otherwise a pick among the steps files there are.
     */
    private function stepsDestination(Output $output, string $default): ?string
    {
        $question = '  <fg=bright-blue>You have undefined steps. Would you like me to generate the steps for you?</>';
        $action = sprintf('append them to %s', $default);
        $files = $this->steps->files();

        if ($files === [] || $this->chooser !== null || $this->generation !== Generation::Asks) {
            return $this->confirm($output, $question, 'generate-steps', $action) ? $default : null;
        }

        return $this->picked($output, $question, $files, $action);
    }

    /**
     * @param list<string> $files the steps files to choose from
     */
    private function picked(Output $output, string $question, array $files, string $action): ?string
    {
        $newFile = count($files) + 1;

        $output->writeln('');
        $output->writeln($question);
        $output->writeln('');
        $output->writeln('  [0] No, skip');
        foreach ($files as $index => $file) {
            $output->writeln(sprintf('  [%d] %s', $index + 1, $this->steps->label($file)));
        }
        $output->writeln(sprintf('  [%d] New file...', $newFile));

        while (($answer = $this->answer($output)) !== null) {
            $choice = trim($answer) === '' ? '1' : trim($answer);
            $index = ctype_digit($choice) ? (int) $choice : -1;

            if ($index === 0) {
                return null;
            }

            if ($index === $newFile) {
                return $this->newStepsFile($output, $action);
            }

            if (isset($files[$index - 1])) {
                return $files[$index - 1];
            }

            $output->writeln(sprintf('  <fg=yellow>Answer with a number from 0 to %d.</>', $newFile));
        }

        return $this->nobody($output, $action);
    }

    private function newStepsFile(Output $output, string $action): ?string
    {
        $output->writeln('  Name the new steps file (for example web):');

        while (($answer = $this->answer($output)) !== null) {
            $file = $this->steps->file($answer);
            if ($file !== null) {
                return $file;
            }

            $output->writeln('  <fg=yellow>A steps file is named, not placed: for example web.</>');
        }

        return $this->nobody($output, $action);
    }

    /**
     * Offers the classes spec examples referenced that do not exist, each
     * introduced by the spec that needs it, where its error would otherwise
     * have been reported.
     *
     * @param Output $output the console output for prompts and confirmation messages
     * @param array<string, string> $missingClasses FQCNs that do not exist, each keyed to the class its spec describes
     * @return list<Applied>
     */
    private function generateMissingSpecClasses(Output $output, array $missingClasses): array
    {
        $applied = [];
        $classGenerator = new ClassGenerator($this->layout);
        $specGenerator = new SpecGenerator($this->specPath, specSuffix: $this->specSuffix);

        foreach ($missingClasses as $fqcn => $describes) {
            $location = ClassLocation::for($fqcn, $this->layout);

            // "Class X not found" from a run is a runtime/autoload failure, not
            // proof the source file is missing: a PSR-4 mismatch triggers it
            // while the file is right there.
            if ($location->exists($this->filesystem)) {
                $output->writeln('');
                $output->writeln(sprintf(
                    '  <fg=yellow>%s exists, but %s could not be autoloaded: check the PSR-4 mapping in composer.json.</>',
                    ProjectRoot::here()->relative($location->filePath()),
                    $fqcn,
                ));

                continue;
            }

            $described = self::describes($describes, $fqcn);
            $output->writeln('');
            $output->writeln(sprintf('  <fg=#f59e0b>%s,</>', $described
                ? "Looks like you are trying to spec $fqcn"
                : "Looks like $describes needs $fqcn"));
            $output->writeln("  <fg=#f59e0b>a class that doesn't exist yet.</>");

            if (!$described) {
                array_push($applied, ...$this->offerSpecThenClass($output, $fqcn, $specGenerator, $classGenerator));

                continue;
            }

            if (!$this->confirm($output, '  <fg=gray>Would you like me to generate that class for you?</>', 'create-class', 'create classes')) {
                continue;
            }

            $reason = $this->generateOrExplain($output, fn() => $classGenerator->generate($fqcn), $location->filePath());
            $applied[] = self::applied('create_class', $fqcn, $location->filePath(), $reason);
        }

        return $applied;
    }

    /**
     * Whether a describe block's title names the class, in full or by its
     * short name.
     */
    private static function describes(string $title, string $fqcn): bool
    {
        return $title === $fqcn || str_ends_with($fqcn, '\\' . $title);
    }

    /**
     * Offers to generate specs and classes for types referenced in steps that don't exist.
     *
     * @param Output $output the console output for prompts and confirmation messages
     * @param array<string> $missingStepClasses FQCNs referenced in steps that do not exist
     * @return list<Applied>
     */
    private function generateMissingStepClasses(Output $output, array $missingStepClasses): array
    {
        $applied = [];
        $specGenerator = new SpecGenerator($this->specPath, specSuffix: $this->specSuffix);
        $classGenerator = new ClassGenerator($this->layout);

        foreach ($missingStepClasses as $fqcn) {
            $output->writeln('');
            $output->writeln(sprintf('  <fg=yellow>Class <fg=white>%s</> not found in step.</>', $fqcn));
            array_push($applied, ...$this->offerSpecThenClass($output, $fqcn, $specGenerator, $classGenerator));
        }

        return $applied;
    }

    /**
     * Offers a spec for a class that does not exist, then the class, the way
     * outside-in reads: what the class is for is said before the class is.
     * A spec that already exists is not offered again.
     *
     * @return list<Applied>
     */
    private function offerSpecThenClass(Output $output, string $fqcn, SpecGenerator $specGenerator, ClassGenerator $classGenerator): array
    {
        $applied = [];
        $specName = str_replace('\\', '/', $fqcn);

        if (!$this->filesystem->exists($specGenerator->filePath($specName))) {
            if (!$this->confirm($output, '  <fg=gray>Do you want me to create a spec for it?</>', 'create-spec', 'create specs')) {
                return [];
            }

            $specGenerator->generate($specName);
            $output->writeln(sprintf('  <fg=green>Spec for %s created in %s</>', $fqcn, ProjectRoot::here()->relative($specGenerator->filePath($specName))));
            $applied[] = self::applied('create_spec', $fqcn, $this->specPath . '/' . $specName . $this->specSuffix);
        }

        $location = ClassLocation::for($fqcn, $this->layout);
        if ($location->exists($this->filesystem)) {
            return $applied;
        }

        $question = sprintf(
            '  <fg=yellow>Do you want me to create class <fg=white>%s</> in <fg=white>%s</>?</>',
            $fqcn,
            ProjectRoot::here()->relative($location->filePath()),
        );

        if (!$this->confirm($output, $question, 'create-class', 'create classes')) {
            return $applied;
        }

        $reason = $this->generateOrExplain($output, fn() => $classGenerator->generate($fqcn), $location->filePath());
        $applied[] = self::applied('create_class', $fqcn, $location->filePath(), $reason);

        return $applied;
    }

    /**
     * Offers to generate interface files for types that mock() couldn't find.
     *
     * @param Output $output the console output for prompts and confirmation messages
     * @param array<string> $missingMockTypes FQCNs that could not be mocked because they do not exist
     * @return list<Applied>
     */
    private function generateMissingInterfaces(Output $output, array $missingMockTypes): array
    {
        $applied = [];
        $interfaceGenerator = new InterfaceGenerator($this->layout);

        foreach (array_unique($missingMockTypes) as $fqcn) {
            $location = ClassLocation::for($fqcn, $this->layout);

            if ($location->exists($this->filesystem)) {
                continue;
            }

            $question = sprintf(
                '  <fg=yellow>Do you want me to create interface <fg=white>%s</> in <fg=white>%s</>?</>',
                $fqcn,
                ProjectRoot::here()->relative($location->filePath()),
            );

            if (!$this->confirm($output, $question, 'create-interface', 'create interfaces')) {
                continue;
            }

            $reason = $this->generateOrExplain($output, fn() => $interfaceGenerator->generate($fqcn), $location->filePath());
            $applied[] = self::applied('create_interface', $fqcn, $location->filePath(), $reason);
        }

        return $applied;
    }

    /**
     * Offers to add method signatures to interfaces when mock doubles call undefined methods.
     *
     * @param Output $output the console output for prompts and confirmation messages
     * @param array<array{className: string, methodName: string, file: string, line: int}> $mockMethods undefined interface-mock methods
     * @return list<Applied>
     */
    private function generateMockInterfaceMethods(Output $output, array $mockMethods): array
    {
        $applied = [];
        $methodGenerator = new MethodStubGenerator($this->layout);

        foreach ($this->uniqueByClassMethod($mockMethods) as $error) {
            $argCount = $this->analyser->extractArgumentCount($error['file'], $error['line'], $error['methodName']);
            $filePath = ClassLocation::for($error['className'], $this->layout)->filePath();

            $question = sprintf(
                '  <fg=yellow>Do you want me to add method <fg=white>%s()</> to interface <fg=white>%s</>?</>',
                $error['methodName'],
                $error['className'],
            );

            if (!$this->confirm($output, $question, 'add-interface-method', 'add interface methods')) {
                continue;
            }

            $reason = $this->generateOrExplain($output, fn() => $methodGenerator->generate($error['className'], $error['methodName'], $argCount), $filePath);
            $applied[] = self::applied('create_method', $error['className'] . '::' . $error['methodName'], $filePath, $reason);
        }

        return $applied;
    }

    /**
     * Offers to generate method stubs on classes for undefined method calls.
     * In --fake mode, includes a hardcoded return value extracted from the spec's toBe() expectation.
     *
     * @param Output $output the console output for prompts and confirmation messages
     * @param array<array{className: string, methodName: string, file: string, line: int}> $classMethods undefined real-class methods
     * @param bool $fake whether --fake mode is enabled
     * @return list<Applied>
     */
    private function generateClassMethods(Output $output, array $classMethods, bool $fake): array
    {
        $applied = [];
        $generator = new MethodStubGenerator($this->layout);

        foreach ($this->uniqueByClassMethod($classMethods) as $error) {
            $argCount = $this->analyser->extractArgumentCount($error['file'], $error['line'], $error['methodName']);
            $static = $this->analyser->isStaticCall($error['file'], $error['line'], $error['methodName']);
            $filePath = ClassLocation::for($error['className'], $this->layout)->filePath();
            $target = $error['className'] . '::' . $error['methodName'];

            $returnExpr = $fake ? $this->analyser->extractExpectedReturnValue($error['file'], $error['line'], $error['methodName']) : null;

            if ($returnExpr !== null) {
                $question = sprintf('  <fg=yellow>Are you sure you want <fg=white>%s()</> to always return <fg=white>%s</>?</>', $error['methodName'], $returnExpr);
                $confirmed = $this->confirm($output, $question, 'confirm-fake-return', 'set fake return values');
            } else {
                $question = sprintf('  <fg=yellow>Do you want me to create <fg=white>%s::%s()</> for you?</>', $error['className'], $error['methodName']);
                $confirmed = $this->confirm($output, $question, 'create-method', 'create methods');
            }

            if (!$confirmed) {
                continue;
            }

            $reason = $this->generateOrExplain($output, fn() => $generator->generate($error['className'], $error['methodName'], $argCount, $returnExpr, $static), $filePath);
            $applied[] = self::applied($returnExpr !== null ? 'fake_method' : 'create_method', $target, $filePath, $reason);
        }

        return $applied;
    }

    /**
     * In --fake mode, offers to fill existing empty method bodies with return expressions
     * derived from failed matcher expectations.
     *
     * @param Output $output the console output for prompts and confirmation messages
     * @param array<array{className: string, methodName: string, fakeExpression: string, file: string, line: int}> $fakeableMethods empty methods that --fake could fill
     * @return list<Applied>
     */
    private function fillEmptyMethods(Output $output, array $fakeableMethods): array
    {
        $applied = [];
        $generator = new MethodStubGenerator($this->layout);

        foreach ($this->uniqueByClassMethod($fakeableMethods) as $candidate) {
            if (!$generator->hasEmptyMethod($candidate['className'], $candidate['methodName'])) {
                continue;
            }

            $filePath = ClassLocation::for($candidate['className'], $this->layout)->filePath();

            $question = sprintf(
                '  <fg=yellow>Are you sure you want <fg=white>%s()</> to always return <fg=white>%s</>?</>',
                $candidate['methodName'],
                $candidate['fakeExpression'],
            );

            if (!$this->confirm($output, $question, 'confirm-fake-return', 'set fake return values')) {
                continue;
            }

            $reason = $this->generateOrExplain($output, fn() => $generator->fillEmptyMethod(
                $candidate['className'],
                $candidate['methodName'],
                $candidate['fakeExpression'],
            ), $filePath);
            $applied[] = self::applied('fake_method', $candidate['className'] . '::' . $candidate['methodName'], $filePath, $reason);
        }

        return $applied;
    }

    /**
     * What one generation wrote, or why it could not, under the id its offer
     * carries: derived from the action and target, as the offer's own is, so
     * the two agree.
     *
     * @return Applied
     */
    private static function applied(string $action, string $target, string $file, ?string $reason = null): array
    {
        $applied = [
            'id' => Offer::generate($action, $target, [])->id,
            'action' => $action,
            'target' => $target,
            'file' => ProjectRoot::here()->relative($file),
            'applied' => $reason === null,
        ];

        if ($reason !== null) {
            $applied['reason'] = $reason;
        }

        return $applied;
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
     * Runs a confirmed generation, showing the diff it made, and answers with
     * why it could not when it could not: a reader who said yes is owed either
     * the change or the reason, never silence.
     *
     * @param Output $output the console output for the result
     * @param callable $generate the generation action to run; returns a success message string
     * @param string|null $filePath path to the file being modified, for diff display
     * @return string|null why nothing was written, or null when it was
     */
    private function generateOrExplain(Output $output, callable $generate, ?string $filePath = null): ?string
    {
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

            return $e->getMessage();
        }

        return null;
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

        if ($this->generation === Generation::Accepts) {
            return true;
        }

        $asking = $this->generation === Generation::Asks;

        $output->writeln('');
        $output->writeln($asking ? "$question [Y/n] " : $question);

        $answer = $asking ? $this->answer($output) : null;

        // Nobody answered: --no-interaction, or a standard input with nothing
        // on it. An unanswered question is not a yes, whatever the default
        // would have been, because reading it as one puts files in a source
        // tree that nobody agreed to. Said out loud, with the way to accept
        // it, because a person reading a log still wants to know what was
        // offered.
        if ($answer === null) {
            $this->nobody($output, $action);

            return false;
        }

        return $answer === '' || strtolower($answer) === 'y';
    }

    /**
     * Says that nothing was written because nobody was there to answer, and
     * how to take the offer anyway.
     */
    private function nobody(Output $output, string $action): null
    {
        $output->writeln(sprintf(
            '  <fg=yellow>Nothing was written: there is nobody to answer. Run with --accept-offers to %s.</>',
            $action,
        ));

        return null;
    }

    /**
     * What the person answered, or nothing when there was nobody there.
     */
    private function answer(Output $output): ?string
    {
        if ($output instanceof ScrollRegionOutput) {
            $output->prepareForInput();
        }

        $answer = $this->prompt->ask('  > ');

        if ($output instanceof ScrollRegionOutput && $answer !== null) {
            $output->returnToContent();
            $output->echoInput($answer ?: 'Y');
        }

        return $answer;
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
        $output->writeln("  <fg=yellow>$label</> <fg=white>" . ProjectRoot::here()->relative($filePath) . '</>');
        $output->writeln('');

        // A proper line diff, so only genuinely new lines are marked "+" — a
        // naive per-index comparison marks every line after an insertion (e.g.
        // the class's closing brace shifting down) as added.
        $output->writeln(Diff::format(Diff::compute($oldLines ?? [], $newLines)));
        $output->writeln('');
    }

    /**
     * Reads user input via readline (TTY) or fgets (pipe).
     *
     * @param string $prompt the prompt string to display
     * @return string the user's input, or empty string if no input
     */
}
