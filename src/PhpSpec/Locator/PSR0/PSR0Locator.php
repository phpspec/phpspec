<?php

namespace PhpSpec\Locator\PSR0;

use PhpSpec\Locator\ResourceLocatorInterface;
use PhpSpec\Util\Filesystem;

use InvalidArgumentException;

class PSR0Locator implements ResourceLocatorInterface
{
    private $srcPath;
    private $specPath;
    private $srcNamespace;
    private $specNamespace;
    private $fullSrcPath;
    private $fullSpecPath;
    private $filesystem;

    public function __construct($srcNamespace = '', $specNamespacePrefix = 'spec',
                                $srcPath = 'src', $specPath = '.', Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?: new Filesystem;
        $sepr = DIRECTORY_SEPARATOR;

        $this->srcPath       = rtrim(realpath($srcPath), '/\\').$sepr;
        $this->specPath      = rtrim(realpath($specPath), '/\\').$sepr;
        $this->srcNamespace  = ltrim(trim($srcNamespace, ' \\').'\\', '\\');
        $this->specNamespace = trim($specNamespacePrefix, ' \\').'\\'.$this->srcNamespace;
        $this->fullSrcPath   = $this->srcPath.str_replace('\\', $sepr, $this->srcNamespace);
        $this->fullSpecPath  = $this->specPath.str_replace('\\', $sepr, $this->specNamespace);

        if ($sepr === $this->srcPath) {
            throw new InvalidArgumentException(sprintf(
                'Source code path should be existing filesystem path, but "%s" given.',
                $srcPath
            ));
        }

        if ($sepr === $this->specPath) {
            throw new InvalidArgumentException(sprintf(
                'Specs code path should be existing filesystem path, but "%s" given.',
                $specPath
            ));
        }
    }

    public function getFullSrcPath()
    {
        return $this->fullSrcPath;
    }

    public function getFullSpecPath()
    {
        return $this->fullSpecPath;
    }

    public function getSrcNamespace()
    {
        return $this->srcNamespace;
    }

    public function getSpecNamespace()
    {
        return $this->specNamespace;
    }

    public function getAllResources()
    {
        return $this->findSpecResources($this->fullSpecPath);
    }

    public function supportsQuery($query)
    {
        $sepr = DIRECTORY_SEPARATOR;
        $path = rtrim(realpath(str_replace(array('\\', '/'), $sepr, $query)), $sepr);

        if (null === $path) {
            return false;
        }

        return 0 === strpos($path, $this->srcPath)
            || 0 === strpos($path, $this->specPath)
        ;
    }

    public function findResources($query)
    {
        $sepr = DIRECTORY_SEPARATOR;
        $path = rtrim(realpath(str_replace(array('\\', '/'), $sepr, $query)), $sepr);

        if ('.php' !== substr($path, -4)) {
            $path .= $sepr;
        }

        if ($path && 0 === strpos($path, $this->fullSrcPath)) {
            $path = $this->fullSpecPath.substr($path, strlen($this->fullSrcPath));
            $path = preg_replace('/\.php/', 'Spec.php', $path);

            return $this->findSpecResources($path);
        }

        if ($path && 0 === strpos($path, $this->srcPath)) {
            $path = $this->fullSpecPath.substr($path, strlen($this->srcPath));
            $path = preg_replace('/\.php/', 'Spec.php', $path);

            return $this->findSpecResources($path);
        }

        if ($path && 0 === strpos($path, $this->specPath)) {
            return $this->findSpecResources($path);
        }

        return array();
    }

    public function supportsClass($classname)
    {
        $classname = str_replace('/', '\\', $classname);

        return '' === $this->srcNamespace
            || 0  === strpos($classname, $this->srcNamespace)
            || 0  === strpos($classname, $this->specNamespace)
        ;
    }

    public function createResource($classname)
    {
        $classname = str_replace('/', '\\', $classname);

        if (0 === strpos($classname, $this->specNamespace)) {
            $relative = substr($classname, strlen($this->specNamespace));

            return new PSR0Resource(explode('\\', $relative), $this);
        }

        if ('' === $this->srcNamespace || 0 === strpos($classname, $this->srcNamespace)) {
            $relative = substr($classname, strlen($this->srcNamespace));

            return new PSR0Resource(explode('\\', $relative), $this);
        }

        return null;
    }

    public function getPriority()
    {
        return 0;
    }

    protected function findSpecResources($path)
    {
        if (!$this->filesystem->pathExists($path)) {
            return array();
        }

        if ('.php' === substr($path, -4)) {
            return array($this->createResourceFromSpecFile(realpath($path)));
        }

        $resources = array();
        foreach ($this->filesystem->findPhpFilesIn($path) as $file) {
            $resources[] = $this->createResourceFromSpecFile($file->getRealPath());
        }

        return $resources;
    }

    private function createResourceFromSpecFile($path)
    {
        // cut "Spec.php" from the end
        $relative = substr($path, strlen($this->fullSpecPath), -4);
        $relative = preg_replace('/Spec$/', '', $relative);

        return new PSR0Resource(explode(DIRECTORY_SEPARATOR, $relative), $this);
    }
}
