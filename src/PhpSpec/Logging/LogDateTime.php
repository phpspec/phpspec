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

namespace PhpSpec\Logging;

use DateTime;

/**
 * Provides microsecond-precision timestamp formatting for log entries.
 */
trait LogDateTime
{
    /**
     * Prepends a bracketed timestamp with microsecond precision to the given log message.
     *
     * @param string $log the log message to timestamp
     * @return string the timestamped log string
     */
    public function getLogNow(string $log): string
    {
        $now = DateTime::createFromFormat('U.u', sprintf('%.6f', microtime(true)));
        if ($now === false) {
            $now = new DateTime();
        }
        return '[' . $now->format('d/m/Y H:i:s.u') . '] - ' . $log;
    }
}
