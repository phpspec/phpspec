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

/**
 * @internal
 * Runs a piece of a run with a hand over the output stream, keeping what it
 * printed rather than letting it land wherever it fell. What the subject prints
 * is a diagnostic belonging to the example or step that provoked it, and under
 * a machine-readable format standard output belongs to the report alone.
 */
final class CapturedOutput
{
    private string $text = '';

    /**
     * Runs the body, keeping everything it printed. Whatever the body does,
     * including throwing, the stream is handed back as it was found.
     *
     * @param \Closure(): mixed $body the piece of the run to listen to
     */
    public function around(\Closure $body): void
    {
        $depth = ob_get_level();
        ob_start();

        try {
            $body();
        } finally {
            // A body that opened a buffer of its own and never closed it does
            // not get to swallow what it printed: unwind back down to ours.
            while (ob_get_level() > $depth + 1) {
                ob_end_flush();
            }

            // Unless it closed ours as well, in which case there is nothing
            // left to take and the text stands at what came before.
            if (ob_get_level() > $depth) {
                $this->text .= (string) ob_get_clean();
            }
        }
    }

    /**
     * Everything the bodies run so far have printed.
     */
    public function text(): string
    {
        return $this->text;
    }
}
