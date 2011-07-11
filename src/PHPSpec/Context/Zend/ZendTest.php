<?php

namespace PHPSpec\Context\Zend;

use \Zend_Test_PHPUnit_ControllerTestCase as ZendControllerTest,
    \Zend_Application as ZendApplication;

class ZendTest extends ZendControllerTest
{
    public function __construct()
    {
        $this->bootstrap = new ZendApplication(
            APPLICATION_ENV, APPLICATION_PATH . '/configs/application.ini');
        parent::setUp();
    }
}