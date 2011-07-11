<?php

namespace PHPSpec\Runner\Formatter;

use \PHPSpec\Runner\Reporter;

class Factory
{
    protected $_formatters = array(
        'p' => 'Progress',
        'd' => 'Documentation',
        'h' => 'Html'
    );
    
    public function create($formatter, Reporter $reporter)
    {
        if (in_array($formatter, array_keys($this->_formatters)) ||
            in_array(ucfirst($formatter), array_values($this->_formatters))) {
            $formatter = $this->_formatters[strtolower($formatter[0])];
            $formatterClass = "\PHPSpec\Runner\Formatter\\" . $formatter;
            return new $formatterClass($reporter);
        }
        return new $formatter;
    }
}