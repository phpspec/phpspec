<?php

namespace PHPSpec\Context\Zend;

require_once 'Zend/Controller/Response/Http.php';

class Response extends \Zend_Controller_Response_Http
{

    protected $_context = null;

    public function setContext(\PHPSpec\Context\Zend $context) 
    {
        $this->_context = $context;
    }

    public function __get($name) 
    {
        if (preg_match("/should/", $name)) {
            return $this->_context->spec($this)->$name;
        }
    }

}