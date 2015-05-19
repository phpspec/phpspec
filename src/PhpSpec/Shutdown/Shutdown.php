<?php

namespace PhpSpec\Shutdown;

class Shutdown
{

    public function __construct()
    {
        register_shutdown_function(array($this, 'updateConsole'));
    }

    public static function register()
    {
        return new Shutdown();
    }

    public function updateConsole()
    {
        echo 'Shutting Down Baby';
    }
}
