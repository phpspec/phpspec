<?php

namespace PhpSpec\Locator\PSR0;

use PhpSpec\Locator\ResourceInterface;

class PSR0Resource implements ResourceInterface
{
    private $parts;
    private $locator;

    public function __construct(array $parts, PSR0Locator $locator)
    {
        $this->parts   = $parts;
        $this->locator = $locator;
    }

    public function getName()
    {
        return end($this->parts);
    }

    public function getSpecName()
    {
        return $this->getName().'Spec';
    }

    public function getSrcFilename()
    {
        return $this->locator->getFullSrcPath().implode(DIRECTORY_SEPARATOR, $this->parts).'.php';
    }

    public function getSrcNamespace()
    {
        $nsParts = $this->parts;
        array_pop($nsParts);

        return rtrim($this->locator->getSrcNamespace().implode('\\', $nsParts), '\\');
    }

    public function getSrcClassname()
    {
        return $this->locator->getSrcNamespace().implode('\\', $this->parts);
    }

    public function getSpecFilename()
    {
        return $this->locator->getFullSpecPath().
            implode(DIRECTORY_SEPARATOR, $this->parts).'Spec.php';
    }

    public function getSpecNamespace()
    {
        $nsParts = $this->parts;
        array_pop($nsParts);

        return rtrim($this->locator->getSpecNamespace().implode('\\', $nsParts), '\\');
    }

    public function getSpecClassname()
    {
        return $this->locator->getSpecNamespace().implode('\\', $this->parts).'Spec';
    }
}
