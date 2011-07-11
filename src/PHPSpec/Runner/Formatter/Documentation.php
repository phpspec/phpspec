<?php

namespace PHPSpec\Runner\Formatter;

class Documentation extends Progress
{
    public function update(\SplSubject $method)
    {
        $args = func_get_args();
        
        switch($args[1][0]) {
            case 'start':
                $this->printLines(1);
                $this->put($args[1][2]);
                break;
            case 'status':
                $this->printLines(1);
                $this->specdocx(
                    $args[1][1], $args[1][2], isset($args[1][3]) ? $args[1][3] : ''
                );
                break;
            case 'exit':
                $this->output();
                exit;
                break;
        }
    }
    
    protected function specdocx($status, $example, $message = '')
    {
        switch($status) {
            case '.':
                $this->put("  " . $this->green($example));
                break;
            case '*':
                $this->put(
                    "  " . $this->yellow($example . " (PENDING: $message)")
                );
                break;
            case 'E':
                static $error = 1;
                $this->put(
                    "  " . $this->red($example . " (ERROR - " . ($error++) .")")
                );
                break;
            case 'F':
                static $failure = 1;
                $this->put(
                    "  " . $this->red(
                        $example . " (FAILED - " . ($failure++) .")"
                    )
                );
                break;
        }
    }
}