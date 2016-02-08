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

namespace PhpSpec\Locator\Suite;

use PhpSpec\Locator\PSR0\PSR0Locator;
use PhpSpec\Locator\ResourceLocatorInterface;
use PhpSpec\Util\Filesystem;

class SuiteLocator extends PSR0Locator implements ResourceLocatorInterface
{
	private $suiteName;

	/**
	 * @param string $srcNamespace
	 * @param string $specNamespacePrefix
	 * @param string $srcPath
	 * @param string $specPath
	 * @param Filesystem $filesystem
	 * @param string $psr4Prefix
	 */
	public function __construct(
		$name = '',
		$srcNamespace = '',
		$specNamespacePrefix = 'spec',
		$srcPath = 'src',
		$specPath = '.',
		Filesystem $filesystem = null,
		$psr4Prefix = null
	)
	{
		$this->suiteName = $name;
		parent::__construct( $srcNamespace, $specNamespacePrefix, $srcPath, $specPath, $filesystem, $psr4Prefix );
	}

	/**
	 * @param string $query
	 *
	 * @return bool
	 */
	public function supportsQuery( $query )
	{
		return $query == $this->suiteName;
	}

	public function findResources( $query )
	{
		return parent::getAllResources();
	}
}
