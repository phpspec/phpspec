<?php

require_once 'SpecHelper.php';

require_once dirname(__FILE__) . '/_zend/application/Bootstrap.php';

class DescribeContextZend extends \PHPSpec\Context
{

    public function itShouldSetControllerNameUsingContextClass()
    {
        $context = new DescribeFooController;
        $this->spec($context->getController())->should->be('Foo');
    }

    public function itShouldCreateFrontControllerWhenInstantiated()
    {
        $context = new DescribeFooController;
        $this->spec($context->getFrontController())->should
            ->beAnInstanceOf('Zend_Controller_Front');
    }

    public function itShouldAllowSettingControllerManually()
    {
        $context = new DescribeFooController;
        $context->setController('Bar');
        $this->spec($context->getController())->should->be('Bar');
    }

    public function itShouldAllowSettingModuleDirs()
    {
        \PHPSpec\Context\Zend::addModuleDirectory('/path/to/module');
        $this->spec(\PHPSpec\Context\Zend::getModuleDirectories())->should
            ->be(array('/path/to/module'));
    }

    public function itShouldAllowSettingSetupCallbackFunction()
    {
        \PHPSpec\Context\Zend::setFrontControllerSetupCallback(array('Bootstrap','prepare'));
        $context = new DescribeFooController;
        $context->beforeEach();
        $this->spec(
                Zend_Controller_Front::getInstance()->getControllerDirectory()
            )->should->be(array('default'=>'./application/controllers'));
    }

//    public function itShouldClearFrontControllerBeforeEachExample()
//    {
//        \PHPSpec\Context\Zend::addModuleDirectory(
//            dirname(__FILE__) . DIRECTORY_SEPARATOR . '_modules'
//        );
//        $context = new DescribeFooController;
//        $context->beforeEach();
//        $this->spec(
//            Zend_Controller_Front::getInstance()->getControllerDirectory()
//            )->should->be(
//                array('Default'=>dirname(__FILE__) . DIRECTORY_SEPARATOR
//                . '_modules' . DIRECTORY_SEPARATOR
//                . 'Default' . DIRECTORY_SEPARATOR
//                . 'controllers')
//        );
//    }
//
//    public function itShouldFormulateAndDispatchGetRequest()
//    {
//        $this->setControllerDirectory();
//        $context = new DescribeFooController;
//        $context->beforeEach();
//        $context->setController('index');
//        $response = $context->get('index');
//        $this->spec($response)->should->match("/This is Index/");
//    }
//
//    public function itShouldFormulateAndDispatchPostRequest()
//    {
//        $this->setControllerDirectory();
//        $context = new DescribeFooController;
//        $context->beforeEach();
//        $context->setController('index');
//        $response = $context->post('index');
//        $this->spec($response)->should->match("/This is Index/");
//    }
//
//    public function itShouldPreserveInstanceOfPhpspecRequest()
//    {
//        $this->setControllerDirectory();
//        $context = new DescribeFooController;
//        $context->beforeEach();
//        $context->setController('index');
//        $response = $context->get('index');
//        $this->spec($context->request())->should->beAnInstanceOf('Zend_Controller_Request_Http');
//    }
//
//    public function itShouldReturnInstanceOfPhpspecResponseFromRequest()
//    {
//        $this->setControllerDirectory();
//        $context = new DescribeFooController;
//        $context->beforeEach();
//        $context->setController('index');
//        $response = $context->get('index');
//        $this->spec($response)->should->beAnInstanceOf('\\PHPSpec\\Context\\Zend\\Response');
//    }
//
//    public function itShouldPreserveInstanceOfPhpspecResponse()
//    {
//        $this->setControllerDirectory();
//        $context = new DescribeFooController;
//        $context->beforeEach();
//        $context->setController('index');
//        $context->get('index');
//        $this->spec($context->response())
//            ->should->beAnInstanceOf('\\PHPSpec\\Context\\Zend\\Response');
//    }
//
//    public function itShouldFormulateAndDispatchRequestWithUserParams()
//    {
//        $this->setControllerDirectory();
//        $context = new DescribeFooController;
//        $context->beforeEach();
//        $context->setController('index');
//        $response = $context->get('userparam', array(), array('text'=>'This is User Param'));
//        $this->spec($response)->should->match("/This is User Param/");
//    }
//
//    public function itShouldDispatchARelativeUriPath()
//    {
//        $this->setControllerDirectory();
//        $context = new DescribeFooController;
//        $context->beforeEach();
//        $response = $context->post('/index/userparam/text/userparampage');
//        $this->spec($response)->should->match("/userparampage/");
//    }
//
//    public function itShouldDispatchRelativePathWithoutOpeningSlash()
//    {
//        $this->setControllerDirectory();
//        $context = new DescribeFooController;
//        $context->beforeEach();
//        $response = $context->post('index/userparam/text/userparampage');
//        $this->spec($response)->should->match("/userparampage/");
//    }
//
//    public function itShouldAttachBesuccessMatcherToResponse()
//    {
//        $context = new DescribeFooController;
//        $response = new \PHPSpec\Context\Zend\Response;
//        $response->setContext($context);
//        $response->setHttpResponseCode(200);
//        $response->should->beSuccess();
//    }
//
//    public function itShouldAttachBesuccessMatcherToResponseAndFailIfNo200ResponseCode()
//    {
//        $context = new DescribeFooController;
//        $response = new \PHPSpec\Context\Zend\Response;
//        $response->setContext($context);
//        $response->setHttpResponseCode(300);
//        $response->shouldNot->beSuccess();
//    }
//
//    public function itShouldAttachHavetextMatcherToResponse()
//    {
//        $context = new DescribeFooController;
//        $response = new \PHPSpec\Context\Zend\Response;
//        $response->setContext($context);
//        $response->setBody('I Am Text');
//        $response->should->haveText('I Am Text');
//    }
//
//    public function after()
//    {
//        \PHPSpec\Context\Zend::clearModuleDirectories();
//        \PHPSpec\Context\Zend::clearControllerDirectories();
//        \PHPSpec\Context\Zend::clearFrontControllerSetupCallback();
//    }
//
//    public function setControllerDirectory()
//    {
//        \PHPSpec\Context\Zend::addControllerDirectory(
//            dirname(__FILE__)
//            . DIRECTORY_SEPARATOR . '_zend'
//            . DIRECTORY_SEPARATOR . 'application'
//            . DIRECTORY_SEPARATOR . 'controllers'
//        );
//    }
}

/**
 * DescribeFooController is just an entry point. The actual controller
 * is manually set so we don't need subclass bloat for specs
 */
class DescribeFooController extends \PHPSpec\Context\Zend
{
}