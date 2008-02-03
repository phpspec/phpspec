<?php

class IndexController extends Zend_Controller_Action
{

    public function indexAction()
    {
        $this->view->text = 'This is Index';
    }

    public function userparamAction() 
    {
        $this->view->text = $this->getRequest()->text;
    }

}