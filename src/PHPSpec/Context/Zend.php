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

    const BASE_URL = 'http://www.example.com';

    protected static $_moduleDirectories = array();

    protected static $_controllerDirectories = array();

    protected static $_frontControllerSetupCallback = null;

    /**
     * Name of the Controller being specified; captured usually
     * from the Context classname.
     *
     * @var string
     */
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

    public static function setFrontControllerSetupCallback($callback)
    {
        self::$_frontControllerSetupCallback = $callback;
    }

    public static function clearFrontControllerSetupCallback()
    {
        self::$_frontControllerSetupCallback = null;
    }

    public static function addModuleDirectory($path)
    {
        self::$_moduleDirectories[] = $path;
    }

    public static function getModuleDirectories()
    {
        return self::$_moduleDirectories;
    }

    public static function clearModuleDirectories()
    {
        self::$_moduleDirectories = array();
    }

    public static function addControllerDirectory($path, $module = null)
    {
        if (!isset($module)) {
        	$module = 'NULL';
        }

        self::$_controllerDirectories[$module] = $path;
    }

    public static function getControllerDirectories()
    {
        return self::$_controllerDirectories;
    }

    public static function clearControllerDirectories()
    {
        self::$_controllerDirectories = array();
    }

    public function beforeEach()
    {
        $_GET = array();
        $_POST = array();
        $_COOKIE = array();
        $this->_clearFrontController();
    }

    public function get($actionName, array $getArray = null, array $paramArray = null)
    {
        if (!empty($getArray)) {
        	$_GET = $getArray;
        }
        $this->_response = $this->_makeRequest($actionName, $paramArray);
        return $this->response();
    }

    public function post($actionName, array $postArray = null, array $paramArray = null)
    {
        if (!empty($postArray)) {
            $_POST = $postArray;
        }
        $this->_response = $this->_makeRequest($actionName, $paramArray);
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

    protected function _makeRequest($actionName, array $paramArray = null)
    {
        if (preg_match("%/%", $actionName)) {
            $uri = self::BASE_URL
                . (substr($actionName, 0, 1) == '/' ? $actionName : '/' . $actionName);
        } else {
            $uri = self::BASE_URL . '/' . $this->getController()
            . (!empty($actionName) ? '/' . $actionName : '');
        }
        $this->_request = new Zend_Controller_Request_Http($uri);
        if (!empty($paramArray)) {
        	$this->request()->setParams($paramArray);
        }
        $response = $this->_frontController->dispatch(
            $this->request(),
            new PHPSpec_Context_Zend_Response
        );
        $response->setContext($this);
        return $response;
    }

    protected function _clearFrontController() {
        $this->_frontController->resetInstance();
        if (count(self::$_frontControllerSetupCallback) > 0) {
            call_user_func(array(
                self::$_frontControllerSetupCallback[0],
                self::$_frontControllerSetupCallback[1]
            ));
        } else {
            $this->_frontController->returnResponse(true);
            $this->_frontController->throwExceptions(true);
            foreach (self::getControllerDirectories() as $module=>$path) {
                if ($module == 'NULL') {
                    $module = null;
                }
                $this->_frontController->addControllerDirectory($path, $module);
            }
            foreach (self::getModuleDirectories() as $path) {
                $this->_frontController->addModuleDirectory($path);
            }
        }

    }

    protected function _setController()
    {
        $controllerClassName = substr(get_class($this), 8);
        $this->_controller = substr($controllerClassName, 0, strlen($controllerClassName)-10);
    }

}