<?php

require_once 'SpecHelper.php';

class DescribeContextZend extends PHPSpec_Context
{

    public function itShouldSetControllerNameUsingContextClass()
    {
        $context = new DescribeFooController;
        $this->spec($context->getController())->should->be('Foo');
    }

    public function itShouldCreateFrontControllerWhenInstantiated()
    {
        $context = new DescribeFooController;
        $this->spec($context->getFrontController())->should->beAnInstanceOf('Zend_Controller_Front');
    }

    public function itShouldAllowSettingControllerManually()
    {
        $context = new DescribeFooController;
        $context->setController('Bar');
        $this->spec($context->getController())->should->be('Bar');
    }

    public function itShouldCreateRequestBeforeEachExample()
    {
        $context = new DescribeFooController;
        $context->beforeEach();
        $this->spec($context->request())->should->beAnInstanceOf('Zend_Controller_Request_http');
    }
}

class DescribeFooController extends PHPSpec_Context_Zend
{
}