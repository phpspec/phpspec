<?php
/**
 * PHPSpec
 *
 * LICENSE
 *
 * This file is subject to the GNU Lesser General Public License Version 3
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/lgpl-3.0.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@phpspec.org so we can send you a copy immediately.
 *
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007 Pádraic Brady, Travis Swicegood
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
namespace PHPSpec\Runner;

use PHPSpec\Runner\Result;
/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007 Pádraic Brady, Travis Swicegood
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
abstract class Reporter
{

    protected $_result; 
    protected $_showColors = false; 
    protected $_doSpecdox = false;

    public function __construct(Result $result)
    {
        $this->_result = $result;
    }

    public function doSpecdox($bool = true)
    {
        $this->_doSpecdox = $bool;
    }

	public function showColors($show)
	{
		$this->_showColors = $show;
	}

    abstract public function toString();

    abstract public function getSpecdox();

    abstract public function __toString();

}