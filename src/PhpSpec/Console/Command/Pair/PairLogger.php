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

namespace PhpSpec\Console\Command\Pair;

/** @internal */

final class PairLogger
{
    private const LOG_FILE = '.phpspec/pair.log';

    /**
     * Appends a timestamped log entry to the pair session log file.
     *
     * @param string $level the log level label (e.g. 'CMD', 'INPUT', 'TOOL', 'RESULT')
     * @param string $message the log message content
     * @return void
     */
    public static function log(string $level, string $message): void
    {
        $logPath = getcwd() . '/' . self::LOG_FILE;
        $dir = dirname($logPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $line = "[$timestamp] [$level] $message\n";
        file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
    }
}
