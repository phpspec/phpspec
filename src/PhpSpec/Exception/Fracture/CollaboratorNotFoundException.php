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

namespace PhpSpec\Exception\Fracture;

use Exception;
use ReflectionParameter;

class CollaboratorNotFoundException extends FractureException
{
    private const CLASSNAME_REGEX = '/\\[.* (?P<classname>[_a-z0-9\\\\]+) .*\\]/i';

    public function __construct(
        string $message,
        private string $collaboratorName,
        int $code = 0,
        Exception $previous = null
    ) {
        parent::__construct($message . ': ' . $this->collaboratorName, $code, $previous);
    }

    public function getCollaboratorName(): string
    {
        return $this->collaboratorName;
    }
    
    private function extractCollaboratorName(ReflectionParameter $parameter): string
    {
        if (preg_match(self::CLASSNAME_REGEX, (string)$parameter, $matches)) {
            return $matches['classname'];
        }

        return 'Unknown class';
    }
}
