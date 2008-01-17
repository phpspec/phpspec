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

require_once 'Zend/Controller/Front.php';
require_once 'Zend/Controller/Request/Http.php';

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007 Pádraic Brady, Travis Swicegood
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class PHPSpec_Context_Zend extends PHPSpec_Context
{
    
    protected static $_moduleDirectories = array();
    
    protected $_controller = '';
    
    /**
     * Zend Framework; instance of Front Controller
     *
     * @var Zend_Controller_Front
     */
    protected $_frontController = null;
    
    /**
     * Zend Framework; instance of HTTP Request
     *
     * @var Zend_Controller_Request_Http
     */
    protected $_request = null;
    
    /**
     * Zend Framework; instance of HTTP Response
     *
     * @var Zend_Controller_Response_Http
     */
    protected $_response = null;
    
    public function __construct()
    {
        parent::__construct();
        $this->_setController();
        $this->_frontController = Zend_Controller_Front::getInstance();
    }
    
    public static function addModuleDirectory($path)
    {
        self::$_moduleDirectories[] = $path;
    }
    
    public static function getModuleDirectories()
    {
        return self::$_moduleDirectories;
    }
    
    public function beforeEach()
    {
        $this->_request = new Zend_Controller_Request_Http;
        $this->_clearFrontController();
    }
    
    public function get($actionName, array $getArray = null, array $paramArray = null)
    {
        $this->request()->setControllerName($this->getController());
        $this->request()->setActionName($actionName);
        if (!empty($getArray)) {
        	$this->request()->setParams($getArray); // override later with subclass!
        }
        if (!empty($paramArray)) {
            $this->request()->setParams($paramArray);
        }
        $this->_response = $this->_frontController->dispatch($this->request());
        return $this->response();
    }
    
    /**
     * Returns current Request_Http object
     *
     * @return Zend_Controller_Request_Http
     */
    public function request()
    {
        return $this->_request;
    }
    
    public function response()
    {
        if (!isset($this->_response)) {
        	throw new PHPSpec_Exception('No response has been retrieved yet;
        	make a get or post request first');
        }
        return $this->_response;
    }
    
    public function setController($controllerName)
    {
        $this->_controller = $controllerName;
    }
    
    public function getController()
    {
        return $this->_controller;
    }
    
    public function getFrontController()
    {
        return $this->_frontController;
    }
    
    protected function _clearFrontController() {
        $this->_frontController->resetInstance();
        $this->_frontController->returnResponse(true);
        foreach (self::getModuleDirectories() as $path) {
        	$this->_frontController->addModuleDirectory($path);
        }
    }
    
    protected function _setController()
    {
        $this->_controller = substr(get_class($this), 8, strlen(substr(get_class($this), 8))-10);
    }
    
}