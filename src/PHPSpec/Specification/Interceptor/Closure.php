<?php

namespace PHPSpec\Specification\Interceptor;

use \PHPSpec\Specification\Interceptor;

class Closure extends Interceptor
{
    /**
     * The closure
     * 
     * @var Closure
     */
    protected $_closure = null;
    
    /**
     * The closure
     * 
     * @var Closure
     */
    protected $_closureException = null;

    /**
     * Scalar is constructed with its value
     * 
     * @param $scalarValue
     */
    public function __construct(\Closure $closure = null)
    {
        if (!is_null($closure)) {
            $this->_closure = $closure;
            try {
                $result = $closure();
                $this->setActualValue($result);
            } catch (\PHPSpec\Exception $e) {
                if (!\PHPSpec\PHPSpec::testingPHPSpec()) {
                    throw $e;
                }
            } catch(\Exception $e) {
                $this->_composedActual = true;
                $this->setActualValue(array(get_class($e), $e->getMessage()));
            }
        }
    }
}