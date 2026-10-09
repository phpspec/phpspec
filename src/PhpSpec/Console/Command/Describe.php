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

namespace PhpSpec\Console\Command;

use PhpSpec\CodeGeneration\PhpName;
use PhpSpec\CodeGeneration\SourceLayout;
use PhpSpec\CodeGeneration\SpecGenerator;
use PhpSpec\Console\Prompt;
use PhpSpec\Report\Formatter\Agent\Schema;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputOption as Option;
use Symfony\Component\Console\Output\OutputInterface as Output;

/**
 * @internal
 * CLI command that generates spec files for a given class. Optionally adds an example
 * for a specific method and can trigger a spec run after generation.
 */
final class Describe extends Command
{
    /**
     * @param SpecGenerator $generator the spec file generator
     * @param string|null $name the command name (defaults to "describe")
     */
    public function __construct(
        private readonly SpecGenerator $generator,
        private readonly SourceLayout $layout = new SourceLayout(),
        private readonly Prompt $prompt = new Prompt(),
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    /**
     * Configures the command name, arguments, and options.
     */
    protected function configure(): void
    {
        $this
            ->setName('describe')
            ->setDefinition([
                new Argument(
                    'class',
                    Argument::OPTIONAL,
                    'Class you want to describe',
                ),
            ])
            ->addOption('exemplify', 'e', Option::VALUE_REQUIRED, 'Add an example for a method')
            ->addOption('run', 'r', Option::VALUE_NONE, 'Run specs after generating')
            ->addOption('agent', null, Option::VALUE_NONE, 'Deprecated alias of --format=agent')
            ->addOption('format', 'f', Option::VALUE_REQUIRED, 'Output format: pretty, or agent (machine-readable JSON receipt for coding agents)', 'pretty')
            ->setDescription('Generate spec for a class');
    }

    /**
     * Generates a spec file, optionally adds a method example, and optionally runs specs.
     *
     * @param Input $input the console input
     * @param Output $output the console output
     * @return int the exit code (0 for success)
     * @throws ExceptionInterface
     */
    protected function execute(Input $input, Output $output): int
    {
        $class = (string) $input->getArgument('class');
        $method = (string) $input->getOption('exemplify');
        $forAgent = $input->getOption('agent') || $input->getOption('format') === 'agent';

        // Refused before anything is written: a spec named after something PHP
        // cannot parse is not a bad spec, it is a file that stops the suite
        // from loading.
        $problem = trim($class) === ''
            ? 'Describe what? Name the class, for example: describe App\\Basket'
            : PhpName::classProblem($class) ?? ($method !== '' ? PhpName::methodProblem($method) : null);

        if ($problem !== null) {
            return $this->refuse($problem, $forAgent, $output);
        }

        $fqcn = $this->placed(str_replace('/', '\\', $class), $input, $output, $forAgent);

        if ($fqcn === null) {
            return 1;
        }

        $spec = str_replace('\\', '/', $fqcn);

        if ($forAgent) {
            return $this->describeForAgent($spec, $input, $output);
        }

        $created = $this->generator->generate($spec);
        $output->writeln('');
        $output->writeln(sprintf(
            '<fg=green>Specification for <fg=yellow>%s</> %s <fg=yellow>%s</></>',
            $fqcn === str_replace('/', '\\', $class) ? $class : $fqcn,
            $created ? 'created in' : 'already exists in',
            $this->specFile($spec),
        ));

        $method = $input->getOption('exemplify');
        if ($method) {
            $added = $this->generator->addExample($spec, $method);
            $output->writeln(sprintf('<fg=green>Example for method <fg=yellow>%s</> %s</>', $method, $added ? 'added.' : 'already exists.'));
        }

        if ($input->getOption('run')) {
            return $this->runAfter($input, $output, []);
        }

        return 0;
    }

    /**
     * Runs the suite the way it was asked for, in the format the describe was
     * asked in, so a reader of the receipt reads the run that followed it on
     * the same channel and gets the run's exit code.
     *
     * @param array<string, mixed> $arguments the run command's arguments
     */
    private function runAfter(Input $input, Output $output, array $arguments): int
    {
        $application = $this->getApplication();
        if ($application === null) {
            return 1;
        }

        $run = new ArrayInput($arguments);
        $run->setInteractive($input->isInteractive());

        return $application->find('run')->run($run, $output);
    }

    /**
     * The spec file for a class path, named from the project root.
     *
     * @param string $spec the class path using forward slashes
     */
    /**
     * The name to describe: as given when it is under a mapped namespace, or
     * when the project maps none; otherwise within the default namespace, or
     * under the mapped namespace the person picks. Null when nobody could
     * pick, said and explained on the way out.
     */
    private function placed(string $fqcn, Input $input, Output $output, bool $forAgent): ?string
    {
        if ($this->layout->mappings() === [] || $this->layout->isMapped($fqcn)) {
            return $fqcn;
        }

        if ($this->layout->defaultNamespace() !== null) {
            return $this->layout->withinDefaultNamespace($fqcn);
        }

        $candidates = $this->layout->candidatesFor($fqcn);
        $problem = sprintf(
            '%s is under none of the mapped namespaces: %s.',
            $fqcn,
            implode(', ', array_map(static fn(string $prefix): string => $prefix . '\\', array_keys($this->layout->mappings()))),
        );
        $remedy = sprintf(
            'Describe %s, name a default_namespace in the phpspec config to put every such name under, or answer when asked which one.',
            $this->oneOf($candidates),
        );

        if ($forAgent || !$input->isInteractive()) {
            $this->refuse($problem, $forAgent, $output, $remedy);

            return null;
        }

        $output->writeln('');
        $output->writeln(sprintf('<fg=yellow>%s</> Describe:', $problem));
        foreach ($candidates as $index => $candidate) {
            $output->writeln(sprintf('  [%d] %s', $index + 1, $candidate));
        }
        $output->writeln(sprintf('  [0] %s, as written', $fqcn));

        while (true) {
            $answer = $this->prompt->ask('  > ');

            if ($answer === null) {
                $this->refuse($problem, false, $output, $remedy);

                return null;
            }

            $answer = trim($answer) === '' ? '1' : trim($answer);

            if ($answer === '0') {
                return $fqcn;
            }

            if (ctype_digit($answer) && isset($candidates[(int) $answer - 1])) {
                return $candidates[(int) $answer - 1];
            }

            $output->writeln(sprintf('  <fg=red>Answer with a number from 0 to %d.</>', count($candidates)));
        }
    }

    /**
     * @param list<string> $names
     */
    private function oneOf(array $names): string
    {
        $last = array_pop($names);

        return $names === [] ? (string) $last : implode(', ', $names) . ' or ' . $last;
    }

    private function specFile(string $spec): string
    {
        return $this->generator->getSpecPath() . '/' . $spec . $this->generator->getSpecSuffix();
    }

    /**
     * Reports a name the command will not write, on whichever channel the
     * caller reads: a machine consumer gets the refusal as its receipt rather
     * than as prose it cannot parse.
     *
     * @param string $problem why the name was refused
     * @param bool $forAgent whether the caller asked for the machine-readable receipt
     * @param Output $output the console output
     * @param string|null $remedy what would get the name written, when there is such a thing
     * @return int the exit code (always 1)
     */
    private function refuse(string $problem, bool $forAgent, Output $output, ?string $remedy = null): int
    {
        if ($forAgent) {
            $json = json_encode(
                ['v' => Schema::V, 'action' => 'describe', 'error' => $problem] + ($remedy === null ? [] : ['remedy' => $remedy]),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ) ?: '{}';
            $output->write($json . "\n", false, Output::OUTPUT_RAW);

            return 1;
        }

        $output->writeln(sprintf('<fg=red>%s</>', $problem));

        if ($remedy !== null) {
            $output->writeln(sprintf('<fg=yellow>%s</>', $remedy));
        }

        return 1;
    }

    /**
     * Scaffolds the spec and emits a single JSON receipt — the class, the spec
     * path, whether it was created, and (with -e) whether the example was added
     * — so a coding agent can scaffold without parsing prose. No file content:
     * the agent reads the file itself.
     *
     * @param string $spec the class path using forward slashes
     * @param Input $input the console input
     * @param Output $output the console output
     * @return int the exit code (0 for success)
     */
    private function describeForAgent(string $spec, Input $input, Output $output): int
    {
        $created = $this->generator->generate($spec);

        $receipt = [
            'v' => Schema::V,
            'action' => 'describe',
            'class' => str_replace('/', '\\', $spec),
            'spec' => $this->specFile($spec),
            'created' => $created,
        ];

        $method = $input->getOption('exemplify');
        if ($method) {
            $receipt['example'] = [
                'method' => $method,
                'added' => $this->generator->addExample($spec, $method),
            ];
        }

        $json = json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $output->write($json . "\n", false, Output::OUTPUT_RAW);

        if ($input->getOption('run')) {
            return $this->runAfter($input, $output, ['--format' => 'agent']);
        }

        return 0;
    }
}
