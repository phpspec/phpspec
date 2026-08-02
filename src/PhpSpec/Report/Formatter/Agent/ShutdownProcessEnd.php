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

namespace PhpSpec\Report\Formatter\Agent;

use Closure;

/**
 * @internal
 * The real process end: PHP's own shutdown, reading whatever error was last
 * raised. Only the errors that end a process are reported; a warning the run
 * survived is the example's business, not the document's.
 */
final class ShutdownProcessEnd implements ProcessEnd
{
    private const ENDS_THE_PROCESS = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;

    public function atEnd(Closure $ending): void
    {
        register_shutdown_function(static function () use ($ending): void {
            $error = error_get_last();
            $fatal = $error !== null && ($error['type'] & self::ENDS_THE_PROCESS) !== 0
                ? new Fatal($error['message'], $error['file'], $error['line'])
                : null;

            $ending($fatal);
        });
    }
}
