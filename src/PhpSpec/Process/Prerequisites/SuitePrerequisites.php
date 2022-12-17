<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Process\Prerequisites;

use PhpSpec\Process\Context\ExecutionContext;

final class SuitePrerequisites implements PrerequisiteTester
{
    public function __construct(private ExecutionContext $executionContext)
    {
    }

    /**
     * @throws PrerequisiteFailedException
     */
    public function guardPrerequisites(): void
    {
        $undefinedTypes = array();

        foreach ($this->executionContext->getGeneratedTypes() as $type) {
            if (!class_exists($type) && !interface_exists($type)) {
                $undefinedTypes[] = $type;
            }
        }

        if ($undefinedTypes) {
            throw new PrerequisiteFailedException(sprintf(
                "The type%s %s %s generated but could not be loaded. Do you need to configure an autoloader?\n",
                \count($undefinedTypes) > 1 ? 's' : '',
                join(', ', $undefinedTypes),
                \count($undefinedTypes) > 1 ? 'were' : 'was'
            ));
        }
    }
}
