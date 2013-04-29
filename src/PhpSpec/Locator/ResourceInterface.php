<?php

namespace PhpSpec\Locator;

interface ResourceInterface
{
    public function getName();
    public function getSpecName();

    public function getSrcFilename();
    public function getSrcNamespace();
    public function getSrcClassname();

    public function getSpecFilename();
    public function getSpecNamespace();
    public function getSpecClassname();
}
