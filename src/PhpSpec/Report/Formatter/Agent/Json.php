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

/**
 * @internal
 * One event as one line of JSON. Whatever the event holds, a line comes out
 * that names the event: a reader decodes it and acts on it, and an empty
 * object is not something anyone can act on.
 */
final class Json
{
    /**
     * Slashes and unicode as they are, the zero fraction kept (a float that
     * lost it would read as the int it was compared against), and a byte that
     * is not UTF-8 replaced rather than fatal to the whole event.
     */
    private const FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE;

    /**
     * @param array<string, mixed> $event
     */
    public static function line(array $event): string
    {
        $json = json_encode($event, self::FLAGS);

        if ($json === false) {
            // What could not be encoded is dropped from the event, and the
            // event says so, keeping its identity and everything else.
            $event['encoding'] = json_last_error_msg();
            $json = (string) json_encode($event, self::FLAGS | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }

        return $json . "\n";
    }
}
