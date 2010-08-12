<?php

require_once 'Zend/Controller/Response/Http.php';

class PHPSpec_Context_Zend_Response extends Zend_Controller_Response_Http
{

    protected $_context = null;

    public function setContext(PHPSpec_Context_Zend $context) 
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