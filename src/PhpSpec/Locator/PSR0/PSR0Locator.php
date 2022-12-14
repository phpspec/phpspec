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

use PhpSpec\Locator\Resource;
use PhpSpec\Locator\ResourceLocator;
use PhpSpec\Locator\SrcPathLocator;
use PhpSpec\Util\Filesystem;
use InvalidArgumentException;

class PSR0Locator implements ResourceLocator, SrcPathLocator
{
    private string $srcPath;
    private string $specPath;
    private string $srcNamespace;
    private string $specNamespace;
    private string $fullSrcPath;
    private string $fullSpecPath;
    private Filesystem $filesystem;

    private ?string $psr4Prefix;

    public function __construct(
        Filesystem $filesystem,
        string $srcNamespace = '',
        string $specNamespacePrefix = 'spec',
        string $srcPath = 'src',
        string $specPath = '.',
        string $psr4Prefix = null
    ) {
        $this->filesystem = $filesystem;
        $sepr = DIRECTORY_SEPARATOR;

        $this->srcPath       = rtrim(realpath($srcPath), '/\\').$sepr;
        $this->specPath      = rtrim(realpath($specPath), '/\\').$sepr;
        $this->srcNamespace  = ltrim(trim($srcNamespace, ' \\').'\\', '\\');
        $this->psr4Prefix    = (null === $psr4Prefix) ? null : ltrim(trim($psr4Prefix, ' \\').'\\', '\\');
        if (null !== $this->psr4Prefix  && !str_starts_with($this->srcNamespace, $this->psr4Prefix)) {
            throw new InvalidArgumentException('PSR4 prefix doesn\'t match given class namespace.'.PHP_EOL);
        }
        $srcNamespacePath = null === $this->psr4Prefix ?
            $this->srcNamespace :
            substr($this->srcNamespace, \strlen($this->psr4Prefix));
        $this->specNamespace = $specNamespacePrefix ?
            trim($specNamespacePrefix, ' \\').'\\'.$this->srcNamespace :
            $this->srcNamespace;
        $specNamespacePath = $specNamespacePrefix ?
            trim($specNamespacePrefix, ' \\').'\\'.$srcNamespacePath :
            $srcNamespacePath;

        $this->fullSrcPath   = $this->srcPath.str_replace('\\', $sepr, $srcNamespacePath);
        $this->fullSpecPath  = $this->specPath.str_replace('\\', $sepr, $specNamespacePath);

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

    
    public function getFullSrcPath(): string
    {
        return $this->fullSrcPath;
    }

    
    public function getFullSpecPath(): string
    {
        return $this->fullSpecPath;
    }

    
    public function getSrcNamespace(): string
    {
        return $this->srcNamespace;
    }

    
    public function getSpecNamespace(): string
    {
        return $this->specNamespace;
    }

    /**
     * @return Resource[]
     */
    public function getAllResources(): array
    {
        return $this->findSpecResources($this->fullSpecPath);
    }

    
    public function supportsQuery(string $query): bool
    {
        $path = $this->getQueryPath($query);

        return str_starts_with($path, $this->srcPath)
            || str_starts_with($path, $this->specPath)
        ;
    }

    public function isPSR4(): bool
    {
        return $this->psr4Prefix !== null;
    }

    /**
     * @return Resource[]
     */
    public function findResources(string $query): array
    {
        $path = $this->getQueryPath($query);

        if (!str_ends_with($path, '.php')) {
            $path .= DIRECTORY_SEPARATOR;
        }

        if ($path && str_starts_with($path, $this->fullSpecPath)) {
            return $this->findSpecResources($path);
        }

        if ($path && str_starts_with($path, $this->fullSrcPath)) {
            $path = $this->fullSpecPath.substr($path, \strlen($this->fullSrcPath));
            $path = preg_replace('/\.php/', 'Spec.php', $path);

            return $this->findSpecResources($path);
        }

        if ($path && str_starts_with($path, $this->srcPath)) {
            $path = $this->fullSpecPath.substr($path, \strlen($this->srcPath));
            $path = preg_replace('/\.php/', 'Spec.php', $path);

            return $this->findSpecResources($path);
        }

        return array();
    }

    
    public function supportsClass(string $classname): bool
    {
        $classname = str_replace('/', '\\', $classname);

        return '' === $this->srcNamespace
            || str_starts_with($classname, $this->srcNamespace)
            || str_starts_with($classname, $this->specNamespace)
        ;
    }

    public function createResource(string $classname): ?PSR0Resource
    {
        $classname = ltrim($classname, '\\');
        $this->validatePsr0Classname($classname);

        $classname = str_replace('/', '\\', $classname);

        if (str_starts_with($classname, $this->specNamespace)) {
            $relative = substr($classname, \strlen($this->specNamespace));

            return new PSR0Resource(explode('\\', $relative), $this);
        }

        if ('' === $this->srcNamespace || str_starts_with($classname, $this->srcNamespace)) {
            $relative = substr($classname, \strlen($this->srcNamespace));

            return new PSR0Resource(explode('\\', $relative), $this);
        }

        return null;
    }

    
    public function getPriority(): int
    {
        return 0;
    }

    /**
     * @return PSR0Resource[]
     */
    protected function findSpecResources(string $path): array
    {
        if (!$this->filesystem->pathExists($path)) {
            return array();
        }

        if (str_ends_with($path, '.php')) {
            return array($this->createResourceFromSpecFile(realpath($path)));
        }

        $resources = array();
        foreach ($this->filesystem->findSpecFilesIn($path) as $file) {
            $resources[] = $this->createResourceFromSpecFile($file->getRealPath());
        }

        return $resources;
    }

    private function findSpecClassname(string $path): ?string
    {
        // Find namespace and class name
        $namespace = '';
        $content   = $this->filesystem->getFileContents($path);
        $tokens    = token_get_all($content);
        $count     = \count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j][0] === T_STRING
                        || ($tokens[$j][0] === T_NAME_FULLY_QUALIFIED || $tokens[$j][0] === T_NAME_QUALIFIED)) {
                        $namespace .= $tokens[$j][1].'\\';
                    } elseif ($tokens[$j] === '{' || $tokens[$j] === ';') {
                        break;
                    }
                }
            }

            if ($tokens[$i][0] === T_CLASS) {
                for ($j = $i+1; $j < $count; $j++) {
                    if ($tokens[$j] === '{') {
                        return $namespace.$tokens[$i+2][1];
                    }
                }
            }
        }

        // No class found
        return null;
    }

    
    private function createResourceFromSpecFile(string $path): PSR0Resource
    {
        $classname = $this->findSpecClassname($path);

        if (null === $classname) {
            throw new \RuntimeException(sprintf('Spec file "%s" does not contains any class definition.', $path));
        }

        // Remove spec namespace from the begining of the classname.
        $specNamespace = trim($this->getSpecNamespace(), '\\').'\\';

        if (!str_starts_with($classname, $specNamespace)) {
            throw new \RuntimeException(sprintf(
                'Spec class `%s` must be in the base spec namespace `%s`.',
                $classname,
                $this->getSpecNamespace()
            ));
        }

        $classname = substr($classname, \strlen($specNamespace));

        // cut "Spec" from the end
        $classname = preg_replace('/Spec$/', '', $classname);

        // Create the resource
        return new PSR0Resource(explode('\\', $classname), $this);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validatePsr0Classname(string $classname): void
    {
        $pattern = '/\A([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*[\/\\\\]?)*[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\z/';

        if (!preg_match($pattern, $classname)) {
            throw new InvalidArgumentException(
                sprintf('String "%s" is not a valid class name.', $classname).PHP_EOL.
                'Please see reference document: '.
                'https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md'
            );
        }
    }

    
    private function getQueryPath(string $query): string
    {
        $sepr = DIRECTORY_SEPARATOR;
        $replacedQuery = str_replace(array('\\', '/'), $sepr, $query);

        if ($this->queryContainsQualifiedClassName($query)) {
            $namespacedQuery = null === $this->psr4Prefix ?
                $replacedQuery :
                substr($replacedQuery, \strlen($this->srcNamespace));

            $path = $this->fullSpecPath . $namespacedQuery . 'Spec.php';

            if ($this->filesystem->pathExists($path)) {
                return $path;
            }
        }

        return rtrim(realpath($replacedQuery), $sepr);
    }

    
    private function queryContainsQualifiedClassName(string $query): bool
    {
        return $this->queryContainsBlackslashes($query) && !$this->isWindowsPath($query);
    }

    
    private function queryContainsBlackslashes(string $query): bool
    {
        return str_contains($query, '\\');
    }

    
    private function isWindowsPath(string $query): bool
    {
        return (bool) preg_match('/^\w:/', $query);
    }
}
