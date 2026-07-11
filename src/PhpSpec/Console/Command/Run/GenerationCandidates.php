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

/**
 * @internal
 * The code-generation opportunities found in a run's results: missing classes,
 * interfaces, methods, step definitions, and --fake candidates.
 *
 * It is a plain, serialisable value so a run can be executed in one process
 * (e.g. a fresh subprocess, so newly generated code is actually loaded) while
 * the interactive generation happens in another — see CommandDispatcher's
 * pair-mode run.
 */
final readonly class GenerationCandidates
{
    /**
     * @param array<string, array<array{keyword: string, text: string}>> $undefinedSteps undefined Gherkin steps grouped by feature file path
     * @param array<string> $missingSpecClasses FQCNs referenced in specs that do not exist
     * @param array<string> $missingStepClasses FQCNs referenced in steps that do not exist
     * @param array<string> $missingMockTypes FQCNs that could not be mocked because they do not exist
     * @param array<array{className: string, methodName: string, file: string, line: int}> $undefinedMockInterfaceMethods methods called on interface mocks that do not exist
     * @param array<array{className: string, methodName: string, file: string, line: int}> $undefinedClassMethods methods called on real classes that do not exist
     * @param array<array{className: string, methodName: string, fakeExpression: string, file: string, line: int}> $fakeableMethods empty methods that --fake could fill
     */
    public function __construct(
        public array $undefinedSteps = [],
        public array $missingSpecClasses = [],
        public array $missingStepClasses = [],
        public array $missingMockTypes = [],
        public array $undefinedMockInterfaceMethods = [],
        public array $undefinedClassMethods = [],
        public array $fakeableMethods = [],
    ) {}

    /**
     * Returns whether there is nothing to generate.
     *
     * @return bool true when every candidate list is empty
     */
    public function isEmpty(): bool
    {
        return $this->undefinedSteps === []
            && $this->missingSpecClasses === []
            && $this->missingStepClasses === []
            && $this->missingMockTypes === []
            && $this->undefinedMockInterfaceMethods === []
            && $this->undefinedClassMethods === []
            && $this->fakeableMethods === [];
    }

    /**
     * Serialises to a plain array suitable for json_encode.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'undefinedSteps' => $this->undefinedSteps,
            'missingSpecClasses' => $this->missingSpecClasses,
            'missingStepClasses' => $this->missingStepClasses,
            'missingMockTypes' => $this->missingMockTypes,
            'undefinedMockInterfaceMethods' => $this->undefinedMockInterfaceMethods,
            'undefinedClassMethods' => $this->undefinedClassMethods,
            'fakeableMethods' => $this->fakeableMethods,
        ];
    }

    /**
     * Reconstructs from a json_decode'd array, tolerating missing keys.
     *
     * @param array<string, mixed> $data the decoded candidate data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['undefinedSteps'] ?? [],
            $data['missingSpecClasses'] ?? [],
            $data['missingStepClasses'] ?? [],
            $data['missingMockTypes'] ?? [],
            $data['undefinedMockInterfaceMethods'] ?? [],
            $data['undefinedClassMethods'] ?? [],
            $data['fakeableMethods'] ?? [],
        );
    }
}
