<?php

namespace PHPSpec\Runner\Formatter;

class Color
{
    protected static $colors = array(
        'green' => "\033[32m%s\033[m",
        'red' => "\033[31m%s\033[m",
        'grey' => "\033[37m%s\033[m",
        'yellow' => "\033[33m%s\033[m",
    );
    
    public static function green($output)
    {
        return sprintf(self::$colors['green'], $output);
    }
    
    public static function red($output)
    {
        return sprintf(self::$colors['red'], $output);        
    }
    
    public static function yellow($output)
    {
        return sprintf(self::$colors['yellow'], $output);        
    }
    
    public static function grey($output)
    {
        return sprintf(self::$colors['grey'], $output);
    }
}