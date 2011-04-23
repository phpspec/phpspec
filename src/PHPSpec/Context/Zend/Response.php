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
namespace PHPSpec\Context\Zend;

/**
 * @see \Zend\Controller\Response\Http
 */
require_once 'Zend/Controller/Response/Http.php';

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Response extends \Zend_Controller_Response_Http
{

    /**
     * The Zend context
     * 
     * @var \PHPSpec\Context\Zend
     */
    protected $_context = null;

    /**
     * Sets the zend context for the response
     * 
     * @param \PHPSpec\Context\Zend $context
     */
    public function setContext(\PHPSpec\Context\Zend $context) 
    {
        $this->_context = $context;
    }

    /**
     * Wraps around the response so it's treated as a
     * {@link \PHPSpec\Specification}
     * 
     * @param string $name
     */
    public function __get($name) 
    {
        if (preg_match("/should/", $name)) {
            return $this->_context->spec($this)->$name;
        }
    }

}