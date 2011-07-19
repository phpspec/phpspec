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
class Object extends Interceptor
{
    /**
     * Proxies call to specification and if method is a dsl call than it calls
     * the interceptor factory for the returned value
     * 
     * @param string $method
     * @param array $args
     * @return \PHPSpec\Specification\Interceptor|boolean
     */
    public function __call($method, $args)
    {
        $dslResult = parent::__call($method, $args);
        if (!is_null($dslResult)) {
            return $dslResult;
        }
        
        $object = $this->getActualValue();;
        return InterceptorFactory::create(
            call_user_func_array(array($object, $method), $args)
        );
    }
}