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

namespace PhpSpec\Event;

use Symfony\Component\EventDispatcher\Event;

final class FileCreationEvent extends Event implements PhpSpecEvent
{
    /**
     * @var string
     */
    private $filepath;

    public function __construct($filepath)
    {

        $this->filepath = $filepath;
    }

    /**
     * @return string
     */
    public function getFilePath(): string
    {
        return $this->filepath;
    }
}
