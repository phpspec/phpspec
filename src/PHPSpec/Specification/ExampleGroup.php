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
 * @copyright Copyright (c) 2010-2012 Pádraic Brady, Travis Swicegood,
 *                                    Marcello Duarte
 * @license   http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
namespace PHPSpec\Specification;

use \PHPSpec\Runner\Reporter,
    \PHPSpec\Specification\Interceptor\InterceptorFactory,
    \PHPSpec\Specification\Result\Pending,
    \PHPSpec\Specification\Result\DeliberateFailure;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2012 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class ExampleGroup
{
    
    /**
     * Override for having it called once before all examples are ran in one
     * group
     */
    public function beforeAll()
    {
        
    }
    
    /**
     * Override for having it called before every example is called in a group
     */
    public function before()
    {
        
    }
    
    /**
     * Override for having it called once after all examples are ran in one
     * group
     */
    public function afterAll()
    {
        
    }
    
    /**
     * Override for having it called after every example is called in a group
     */
    public function after()
    {
        
    }
    
    /**
     * Encapsulate result with a interceptor to be able to add expectations
     * to the values
     * 
     * @return \PHPSpec\Specification\Interceptor
     */
    public function spec()
    {
        return call_user_func_array(
            array(
                '\PHPSpec\Specification\Interceptor\InterceptorFactory',
                'create'),
            func_get_args()
        );
    }
    
    /**
     * Marks example as pending
     * 
     * @param string $message
     * @throws \PHPSpec\Specification\Result\Pending
     */
    public function pending($message = 'No reason given')
    {
        throw new Pending($message);
    }
    
    /**
     * Marks example as failure
     * 
     * @param string $message
     * @throws \PHPSpec\Specification\Result\DeliberateFailure
     */
    public function fail($message = '')
    {
        $message = empty($message) ? '' : PHP_EOL . '       ' . $message;
        throw new DeliberateFailure(
            'RuntimeError:' . $message
        );
    }
    
    /**
     * Wrapper for {@link \PHPSpec\Mocks\Mock} factory
     * 
     * @param string $class
     * @param array  $stubs
     * @param array  $arguments
     * @return object
     */
    public function double($class = 'stdClass')
    {
        if (class_exists('Mockery')) {
            $double = \Mockery::mock($class);
            return $double;
        }
        throw new \PHPSpec\Exception('Mockery is not installed');
    }

    /**
     * Wrapper for {@link \PHPSpec\Mocks\Mock} factory
     * 
     * @param string $class
     * @param array  $stubs
     * @param array  $arguments
     * @return object
     */
    public function mock($class = 'stdClass')
    {
        return $this->double($class);
    }

    /**
     * Wrapper for {@link \PHPSpec\Mocks\Mock} factory
     * 
     * @param string $class
     * @param array  $stubs
     * @param array  $arguments
     * @return object
     */
    public function stub($class = 'stdClass')
    {
        return $this->double($class, $stubs, $arguments);
    }
}