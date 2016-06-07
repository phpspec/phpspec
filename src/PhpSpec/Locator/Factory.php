<?php

namespace PhpSpec\Locator;

use PhpSpec\Locator\PSR0\PSR0Locator;
use PhpSpec\Util\Filesystem;

class Factory
{
    private $fileSystem;

    public function __construct(Filesystem $fileSystem)
    {
        $this->fileSystem = $fileSystem;
    }

    /**
     * @param string $suite
     * @return PSR0Locator
     */
    public function buildLocatorForSuite($suite)
    {
        $suite      = is_array($suite) ? $suite : array('namespace' => $suite);
        $defaults = array(
            'namespace'     => '',
            'spec_prefix'   => 'spec',
            'src_path'      => 'src',
            'spec_path'     => '.',
            'psr4_prefix'   => null
        );

        $config = array_merge($defaults, $suite);

        if (!is_dir($config['src_path'])) {
            mkdir($config['src_path'], 0777, true);
        }
        if (!is_dir($config['spec_path'])) {
            mkdir($config['spec_path'], 0777, true);
        }

        return new PSR0Locator(
            $this->fileSystem,
            $config['namespace'],
            $config['spec_prefix'],
            $config['src_path'],
            $config['spec_path'],
            $config['psr4_prefix']
        );
    }
}
