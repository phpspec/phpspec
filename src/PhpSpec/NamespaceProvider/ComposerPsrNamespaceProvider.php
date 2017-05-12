<?php

namespace PhpSpec\NamespaceProvider;

use Composer\Autoload\ClassLoader;

/**
 * Provides project namespaces and where to find them.
 */
class ComposerPsrNamespaceProvider
{
    /**
     * @var string path to the root directory of the project, without a trailing slash
     */
    private $rootDirectory;

    /**
     * @var string prefix of the specifications namespace
     */
    private $specPrefix;

    public function __construct($rootDirectory, $specPrefix)
    {
        $this->rootDirectory = $rootDirectory;
        $this->specPrefix = $specPrefix;
    }

    /**
     * @return string[] a map associating a namespace to a location, e.g
     *                  [
     *                      'My\PSR4Namespace' => 'my/PSR4Namespace',
     *                      'My\PSR0Namespace' => '',
     *                  ]
     */
    public function getNamespaces()
    {
        $vendors = array();
        foreach (get_declared_classes() as $class) {
            if ('C' === $class[0] && 0 === strpos($class, 'ComposerAutoloaderInit')) {
                $r = new \ReflectionClass($class);
                $v = dirname(dirname($r->getFileName()));
                if (file_exists($v.'/composer/installed.json')) {
                    $vendors[] = $v;
                }
            }
        }
        $classLoader = require $this->rootDirectory . '/vendor/autoload.php';
        return
            $this->getNamespacesFromPrefixes($classLoader->getPrefixes(), $vendors)
            +
            $this->getNamespacesFromPrefixes($classLoader->getPrefixesPsr4(), $vendors);
    }

    private function getNamespacesFromPrefixes(array $prefixes, array $vendors)
    {
        $namespaces = array();
        foreach ($prefixes as $namespace => $psrPrefix) {
            foreach ($psrPrefix as $location) {
                foreach ($vendors as $vendor) {
                    if (strpos(realpath($location), $vendor) === 0) {
                        break 2;
                    }
                }
                if (strpos($namespace, $this->specPrefix) !== 0) {
                    $namespaces[$namespace] = substr(
                        realpath($location),
                        strlen(realpath($this->rootDirectory)) + 1 // trailing slash
                    );
                }
            }
        }

        return $namespaces;
    }
}
