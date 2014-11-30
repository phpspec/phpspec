<?php

namespace PhpSpec\Process;

class ErrorHandler
{
    private $currentExample;

    /**
     * @var
     */
    private $context;

    /**
     * @var Rerunner
     */
    private $rerunner;

    public function __construct(RerunContext $context, Rerunner $rerunner)
    {
        $this->context = $context;
        $this->rerunner = $rerunner;
    }

    public function init()
    {
        error_reporting(E_ALL & ~E_ERROR & ~E_PARSE& ~E_COMPILE_ERROR);
        register_shutdown_function(array($this, 'shutdown'));
    }

    public function shutdown()
    {
        if ($this->currentExample && $error = error_get_last()) {
            if (in_array($error['type'], array(E_ERROR, E_PARSE, E_COMPILE_ERROR))) {
                $this->handleError($error);
            }
        }
    }

    public function setCurrentExample($class, $function)
    {
        $this->currentExample = array($class, $function);
    }

    public function clearCurrentExample()
    {
        $this->currentExample = null;
    }

    /**
     * @param $error
     */
    private function handleError($error)
    {
        $this->context->setFatalSpec($this->currentExample[0], $this->currentExample[1], $error);
        $this->rerunner->reRunSuite();
    }

} 