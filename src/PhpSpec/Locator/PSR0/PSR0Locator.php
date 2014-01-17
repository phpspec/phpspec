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

namespace PhpSpec\Locator\PSR0;

use PhpSpec\Locator\ResourceInterface;
use PhpSpec\Locator\ResourceLocatorInterface;
use PhpSpec\Util\Filesystem;

use InvalidArgumentException;

/**
 * Class PSR0Locator
 * @package PhpSpec\Locator\PSR0
 */
class PSR0Locator implements ResourceLocatorInterface
{
    /**
     * @var string
     */
    private $srcPath;
    /**
     * @var string
     */
    private $specPath;
    /**
     * @var string
     */
    private $srcNamespace;
    /**
     * @var string
     */
    private $specNamespace;
    /**
     * @var string
     */
    private $fullSrcPath;
    /**
     * @var string
     */
    private $fullSpecPath;
    /**
     * @var \PhpSpec\Util\Filesystem
     */
    private $filesystem;

    /**
     * @param string     $srcNamespace
     * @param string     $specNamespacePrefix
     * @param string     $srcPath
     * @param string     $specPath
     * @param Filesystem $filesystem
     */
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

    /**
     * @return string
     */
    public function getFullSrcPath()
    {
        return $this->fullSrcPath;
    }

    /**
     * @return string
     */
    public function getFullSpecPath()
    {
        return $this->fullSpecPath;
    }

    /**
     * @return string
     */
    public function getSrcNamespace()
    {
        return $this->srcNamespace;
    }

    /**
     * @return string
     */
    public function getSpecNamespace()
    {
        return $this->specNamespace;
    }

    /**
     * @return ResourceInterface[]
     */
    public function getAllResources()
    {
        return $this->findSpecResources($this->fullSpecPath);
    }

    /**
     * @param string $query
     *
     * @return bool
     */
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

    /**
     * @param string $query
     *
     * @return ResourceInterface[]
     */
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

    /**
     * @param string $classname
     *
     * @return bool
     */
    public function supportsClass($classname)
    {
        $classname = str_replace('/', '\\', $classname);

        return '' === $this->srcNamespace
            || 0  === strpos($classname, $this->srcNamespace)
            || 0  === strpos($classname, $this->specNamespace)
        ;
    }

    /**
     * @param string $classname
     *
     * @return null|PSR0Resource
     */
    public function createResource($classname)
    {
        $this->validatePsr0Classname($classname);

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

    /**
     * @return int
     */
    public function getPriority()
    {
        return 0;
    }

    /**
     * @param string $path
     *
     * @return PSR0Resource[]
     */
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

    /**
     * @param string $path
     *
     * @return PSR0Resource
     */
    private function createResourceFromSpecFile($path)
    {
        // cut "Spec.php" from the end
        $relative = substr($path, strlen($this->fullSpecPath), -4);
        $relative = preg_replace('/Spec$/', '', $relative);

        return new PSR0Resource(explode(DIRECTORY_SEPARATOR, $relative), $this);
    }

    private function validatePsr0Classname($classname)
    {
        $classnamePattern = '/^([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*[\/\\\\]?)*[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/';

        if (!preg_match($classnamePattern, $classname)) {
            throw new InvalidArgumentException(
                sprintf('String "%s" is not a valid class name.', $classname) . PHP_EOL .
                'Please see reference document: ' .
                'https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md'
            );
        }
    }
}
