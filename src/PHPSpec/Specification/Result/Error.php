<?php

namespace PHPSpec\Specification\Result;

use \PHPSpec\Specification\Result;

class Error extends Result
{
    /**
     * @param string  $message
     * @param integer $code
     * @param string  $file
     * @param integer $line
     * @param array   $backtrace
     */
    public function __construct($message = null, $code = 0, $file = null,
                                $line = null, $backtrace = null)
    {
        parent::__construct($message, $code);
        if (!is_null($file)) {
            $this->file = $file;
        }
        if (!is_null($line)) {
            $this->line = $line;
        }
        if (!is_null($backtrace)) {
            $this->trace = $backtrace;
        }
    }
    
    /**
     * Gets the error type based on the error code
     * 
     * @return string
     */
    public function getErrorType()
    {
        switch ($this->code) {
            case E_ERROR:
                return 'PHP Error';
                break;
        
            case E_WARNING:
                return 'PHP Warning';
                break;
        
            case E_NOTICE:
                return 'PHP Notice';
                break;
        
            case E_DEPRECATED:
                return 'PHP Deprecated';
                break;
                
            case E_USER_ERROR:
                return 'User Error';
                break;
        
            case E_USER_WARNING:
                return 'User Warning';
                break;
        
            case E_USER_NOTICE:
                return 'User Notice';
                break;
        
            case E_USER_DEPRECATED:
                return 'User Deprecated';
                break;
        
            default:
                return 'Unknown';
                break;
        }
    }
}