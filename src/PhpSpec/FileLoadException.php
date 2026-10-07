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

namespace PhpSpec;

use RuntimeException;
use Throwable;

/**
 * @internal
 * A step or support file that could not be loaded: a parse error, or a class
 * built on a type that does not exist yet. The run stops with the file and the
 * reason, where PHP alone would have died with a stack trace.
 */
final class FileLoadException extends RuntimeException
{
    public function __construct(string $file, Throwable $cause)
    {
        parent::__construct(sprintf('%s could not load: %s', ProjectRoot::here()->relative($file), $cause->getMessage()), 0, $cause);
    }

    public function remedy(): string
    {
        return 'Fix the file, or create what it needs before the features run; a class under features/support loads before any step.';
    }
}
