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

namespace PhpSpec\Exception\Wrapper;

use PhpSpec\Exception\Exception;

/**
 * Class MatcherNotFoundException holds information about matcher not found
 * exception
 */
class MatcherNotFoundException extends Exception
{
    public function __construct(string $message, private string $keyword, private mixed $subject, private array $arguments)
    {
        parent::__construct($message);
    }

    public function getKeyword(): string
    {
        return $this->keyword;
    }

    public function getSubject(): mixed
    {
        return $this->subject;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }
}
