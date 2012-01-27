<?php

namespace Spec\PHPSpec\Loader;

use \PHPSpec\Loader\DirectoryLoader,
    \PHPSpec\Util\SpecIterator;

class DescribeDirectoryLoader extends \PHPSpec\Context
{
    function itLoadsAllExampleGroupsUnderADirectory()
    {
        $loader = new DirectoryLoader;
        $examples = $loader->load(__DIR__ . '/_files/Bar');
        
        $examples = new SpecIterator($examples);
        
        $i = array('A', 'B', 'C');
        $examples -> withEach ( function ($example) use (&$i) {
            $example->should->beAnInstanceOf('Describe' . current($i));
            next($i);
        });
    }
}