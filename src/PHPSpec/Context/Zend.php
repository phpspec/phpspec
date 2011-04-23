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
namespace PHPSpec\Context;

/**
 * @see \Zend\Controller\Front
 */
require_once 'Zend/Controller/Front.php';

/**
 * @see \Zend\Controller\Request\Http
 */
require_once 'Zend/Controller/Request/Http.php';

// require_once 'Zend/Di.php';

use \PHPSpec\Context;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Zend extends Context
{

    /**
     * @FIXME! This is probably not meant to be a constant?
     */
    const BASE_URL = 'http://www.example.com';

    /**
     * Directories where the modules are
     * 
     * @var array
     */
    protected static $_moduleDirectories = array();

    /**
     * Directories where the controllers are
     * 
     * @var array
     */
    protected static $_controllerDirectories = array();

    /**
     * Callback invoked before each example when front controller is reset
     * 
     * @var string|array|Closure
     */
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
     * @var \Zend_Controller_Front
     */
    protected $_frontController = null;

    /**
     * Zend Framework; instance of HTTP Request
     *
     * @var \Zend_Controller_Request_Http
     */
    protected $_request = null;

    /**
     * Zend Framework; instance of HTTP Response
     *
     * @var \Zend_Controller_Response_Http
     */
    protected $_response = null;

    /**
     * Zend controller context is constructed and a front controller singleton
     * is stored as context property
     */
    public function __construct()
    {
        parent::__construct();
        $this->_setController();
        $this->_frontController = \Zend_Controller_Front::getInstance();
    }

    /**
     * Sets the front controller callback
     * 
     * @param string|array|Closure $callback
     */
    public static function setFrontControllerSetupCallback($callback)
    {
        self::$_frontControllerSetupCallback = $callback;
    }

    /**
     * Clears front controller setup callback
     */
    public static function clearFrontControllerSetupCallback()
    {
        self::$_frontControllerSetupCallback = null;
    }

    /**
     * Adds a path to modules directories
     * 
     * @param string $path
     */
    public static function addModuleDirectory($path)
    {
        self::$_moduleDirectories[] = $path;
    }

    /**
     * Returns the module directories array
     * 
     * @return array
     */
    public static function getModuleDirectories()
    {
        return self::$_moduleDirectories;
    }

    /**
     * Clears the module directories property
     */
    public static function clearModuleDirectories()
    {
        self::$_moduleDirectories = array();
    }

    /**
     * Add the controller directory of a given module. Default is the 'NULL'
     * string value
     * 
     * @param string $path
     * @param string $module
     */
    public static function addControllerDirectory($path, $module = null)
    {
        if (!isset($module)) {
            $module = 'NULL';
        }

        self::$_controllerDirectories[$module] = $path;
    }

    /**
     * Returns the controller directories array
     * 
     * @return array
     */
    public static function getControllerDirectories()
    {
        return self::$_controllerDirectories;
    }

    /**
     * Clears the controller directories property
     */
    public static function clearControllerDirectories()
    {
        self::$_controllerDirectories = array();
    }

    /**
     * Clears request super global variables and runs front controller setup
     * callback
     */
    public function beforeEach()
    {
        $_GET = array();
        $_POST = array();
        $_COOKIE = array();
        $this->_clearFrontController();
    }

    /**
     * Sends a get type request. Fills <code>$_GET</code> super global then
     * makes a request
     * 
     * @param string $actionName
     * @param array  $getArray
     * @param array  $paramArray
     * @return \Zend_Controller_Response_Http
     */
    public function get($actionName, array $getArray = null,
                        array $paramArray = null)
    {
        if (!empty($getArray)) {
            $_GET = $getArray;
        }
        $this->_response = $this->_makeRequest($actionName, $paramArray);
        return $this->response();
    }

    /**
     * Sends a post type request. Fills <code>$_POST</code> super global then
     * makes a request
     * 
     * @param string $actionName
     * @param array $postArray
     * @param array $paramArray
     * @return \Zend_Controller_Response_Http
     */
    public function post($actionName, array $postArray = null,
                         array $paramArray = null)
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
     * @return \Zend_Controller_Request_Http
     */
    public function request()
    {
        return $this->_request;
    }

    /**
     * Returns the current response object
     * 
     * @return Zend_Controller_Response_Http
     * @throws \PHPSpec\Exception if not response has been retrieved yet
     */
    public function response()
    {
        if (!isset($this->_response)) {
            throw new \PHPSpec\Exception(
                'No response has been retrieved yet;
                make a get or post request first'
            );
        }
        return $this->_response;
    }
    
    /**
     * Injects an object
     * 
     * @deprecated as Zend_Di never left the incubator
     */
    public function replaceClass()
    {
        $args = func_get_args();
        $method = new \ReflectionMethod("\\Zend_Di", 'replaceClass');
        $method->invokeArgs(null, $args);
    }

    /**
     * Sets the controller name
     * 
     * @param string $controllerName
     */
    public function setController($controllerName)
    {
        $this->_controller = $controllerName;
    }

    /**
     * Gets the controller
     * 
     * @return string
     */
    public function getController()
    {
        return $this->_controller;
    }

    /**
     * Gets the front controller
     * 
     * @return \Zend_Controller_Front
     */
    public function getFrontController()
    {
        return $this->_frontController;
    }

    /**
     * Makes a request
     * 
     * @param string $actionName
     * @param array  $paramArray
     * @return \Zend_Controller_Response_Http
     */
    protected function _makeRequest($actionName, array $paramArray = null)
    {
        if (preg_match("%/%", $actionName)) {
            $uri = self::BASE_URL . (substr($actionName, 0, 1) == '/' ?
                                     $actionName :
                                     '/' . $actionName);
        } else {
            $uri = self::BASE_URL . '/' . $this->getController()
            . (!empty($actionName) ? '/' . $actionName : '');
        }
        $this->_request = new \Zend_Controller_Request_Http($uri);
        if (!empty($paramArray)) {
            $this->request()->setParams($paramArray);
        }
        $response = $this->_frontController->dispatch(
            $this->request(),
            new \PHPSpec\Context\Zend\Response
        );
        $response->setContext($this);
        return $response;
    }
    
    /**
     * Returns the callback size, 2 for array callback, 1 for strings or
     * anonymous functions and 0 for no callback.
     * 
     * @return integer
     */
    private function callbackSize()
    {
        if (is_array(self::$_frontControllerSetupCallback)) {
            return count(self::$_frontControllerSetupCallback);
        }
        if ((is_string(self::$_frontControllerSetupCallback) &&
            trim(self::$_frontControllerSetupCallback) !== '') ||
            is_callable(self::$_frontControllerSetupCallback)) {
            return 1;
        }
        return 0;
    }

    /**
     * Runs the front controller setup callback. If none is set runs the default
     * which sets <code>returnResponse</code>, <code>throwExceptions</code>,
     * <code>controllerDirectories</code> and <code>modulesDirectory</code>
     */
    protected function _clearFrontController()
    {
        $this->_frontController->resetInstance();
 
        switch ($this->callbackSize()) {
        case 2:
            call_user_func(
                array(
                    self::$_frontControllerSetupCallback[0],
                    self::$_frontControllerSetupCallback[1]
                )
            );
            break;
        case 1:
            call_user_func(self::$_frontControllerSetupCallback);
            break;
        case 0:
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
            break;
        default:
            throw new \RuntimeException(
                'Invalid callback for front controller setup'
            );
        }

    }

    /**
     * Sets the controller based on the context name
     */
    protected function _setController()
    {
        if (strpos(strtolower(get_class($this)), 'describe') === 0) {
            $controllerClassName = substr(get_class($this), 8);

        } else { // ends with spec
            $controllerClassName = substr(
                get_class($this), 0, strlen(get_class($this)) - 4
            );
        }
        
        $this->_controller = substr(
            $controllerClassName, 0, strlen($controllerClassName)-10
        );
    }

}