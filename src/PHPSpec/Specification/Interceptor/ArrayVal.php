<?php

namespace PHPSpec\Specification\Interceptor;

use \PHPSpec\Specification\Interceptor;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class ArrayVal extends Interceptor implements \ArrayAccess
{
	
	public function offsetExists($offset) {
		if (isset($this->_actualValue[$offset])) {
			return true;
		}
		
		return false;
	}
	
	public function offsetGet($offset) {
		if (!isset($this[$offset])) {
			return InterceptorFactory::create(false);
		}
		
		return InterceptorFactory::create($this->_actualValue[$offset]);
	}
	
	public function offsetSet($offset, $value) {
		$this->_actualValue[$offset] = $value;
	}
	
	public function offsetUnset($offset) {
		if (isset($this[$offset])) {
			unset($this->_actualValue[$offset]);
		}
	}
	
} 