<?php

require_once 'Zend/Controller/Action.php';

class PhpspecController extends Zend_Controller_Action
{
    public function pgetController()
    {
        $this->getResponse()->setBody(
            $this->getRequest()->text
        );
    }
}