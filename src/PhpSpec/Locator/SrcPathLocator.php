<?php
namespace PhpSpec\Locator;

interface SrcPathLocator
{
    /**
     * @return string
     */
    public function getFullSrcPath(): string;
}
