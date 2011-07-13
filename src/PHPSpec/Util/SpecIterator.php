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
 * to license@phpspec.net so we can send you a copy immediately.
 *
 * @category  PHPSpec
 * @package   PHPSpec
 * @copyright Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                    Marcello Duarte
 * @license   http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
namespace PHPSpec\Util;

use \PHPSpec\Specification\Interceptor\InterceptorFactory;

class SpecIterator implements \Iterator
{
    protected $_value;
    
    public function __construct($value)
    {
        $this->_value = $value;
    }
    
    public function next()
    {
        next($this->_value);
    }
    public function current()
    {
        return current($this->_value);
    }
    public function valid()
    {
        return !@is_null($this->_value[key($this->_value)]);
    }
    public function key()
    {
        return key($this->_value);
    }
    public function rewind()
    {
        reset($this->_value);
    }

    public function withEach(\Closure $yield)
    {
        $elements = array();
        if (is_array($this->_value) || $this->_value instanceof Iterator) {
            foreach ($this->_value as $key => $value) {
                $elements[] = $yield(InterceptorFactory::create($value));
            }
            return $elements;
        }
        throw new \PHPSpec\Exception('Not an traversable item');
    }
}