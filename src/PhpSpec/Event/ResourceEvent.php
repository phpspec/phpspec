<?php
declare(strict_types=1);

namespace PhpSpec\Event;

use Symfony\Component\EventDispatcher\Event;
use PhpSpec\Locator\Resource;

final class ResourceEvent extends Event implements PhpSpecEvent
{
    const LOADED = 0;

    const IGNORED = 1;

    private $resource;

    private $result;

    public function __construct(Resource $resource, int $result)
    {
        $this->resource = $resource;

        // TODO (2019-10-25 10:51 by Gildas): add an allowed value check on $result
        $this->result = $result;
    }

    public function getResource(): Resource
    {
        return $this->resource;
    }

    public function isIgnored(): bool
    {
        return self::IGNORED === $this->result;
    }
}
