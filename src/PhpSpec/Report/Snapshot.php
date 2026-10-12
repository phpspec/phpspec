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

namespace PhpSpec\Report;

use PhpSpec\ObjectName;

/**
 * @internal
 * A value as it stood when it was compared: every object in it, however deep,
 * kept as it was named and shown then, so a report written later reads what
 * the expectation saw and not what the example did to the object afterwards.
 */
final class Snapshot
{
    private const DEPTH_MAX = 64;

    public static function of(mixed $value, int $depth = 0): mixed
    {
        if ($value instanceof ReportedObject) {
            return $value;
        }

        if (is_object($value) || is_resource($value)) {
            return new ReportedObject(
                is_object($value) ? ObjectName::of($value) : sprintf('resource (%s)', get_resource_type($value)),
                Typed::value($value),
            );
        }

        if (!is_array($value)) {
            return $value;
        }

        if ($depth >= self::DEPTH_MAX) {
            return new ReportedObject('array', Typed::value($value));
        }

        return array_map(static fn(mixed $item): mixed => self::of($item, $depth + 1), $value);
    }
}
