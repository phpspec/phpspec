<?php

namespace PhpSpec\CodeGenerator;

use PhpSpec\Util\Filesystem;

class TemplateRenderer
{
    private $locations = array();

    public function __construct(Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?: new Filesystem;
    }

    public function setLocations(array $locations)
    {
        $this->locations = array_map(array($this, 'normalizeLocation'), $locations);
    }

    public function prependLocation($location)
    {
        array_unshift($this->locations, $this->normalizeLocation($location));
    }

    public function appendLocation($location)
    {
        array_push($this->locations, $this->normalizeLocation($location));
    }

    public function getLocations()
    {
        return $this->locations;
    }

    public function render($name, array $values = array())
    {
        foreach ($this->locations as $location) {
            $path = $location.DIRECTORY_SEPARATOR.$this->normalizeLocation($name, true).'.tpl';

            if ($this->filesystem->pathExists($path)) {
                return $this->renderString($this->filesystem->getFileContents($path), $values);
            }
        }
    }

    public function renderString($template, array $values = array())
    {
        return strtr($template, $values);
    }

    private function normalizeLocation($location, $trimLeft = false)
    {
        $location = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $location);
        $location = rtrim($location, DIRECTORY_SEPARATOR);

        if ($trimLeft) {
            $location = ltrim($location, DIRECTORY_SEPARATOR);
        }

        return $location;
    }
}
