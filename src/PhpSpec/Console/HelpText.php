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

namespace PhpSpec\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Descriptor\TextDescriptor;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * @internal
 * The text help of a command, without the options it keeps for itself.
 */
final class HelpText extends TextDescriptor
{
    /** @var list<string> */
    private array $internal = [];

    /**
     * @param array<string, mixed> $options
     */
    protected function describeCommand(Command $command, array $options = []): void
    {
        $this->internal = $command instanceof InternalOptions ? $command->internalOptions() : [];

        parent::describeCommand($command, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function describeInputDefinition(InputDefinition $definition, array $options = []): void
    {
        $shown = array_filter(
            $definition->getOptions(),
            fn(InputOption $option): bool => !in_array($option->getName(), $this->internal, true),
        );

        parent::describeInputDefinition(new InputDefinition([...array_values($definition->getArguments()), ...array_values($shown)]), $options);
    }
}
