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

        $count = 0;
        $examples->withEach( function ($example) use (&$count) {
            $example->should->beAnInstanceOf('PHPSpec\\Context');
            $count++;
        });
        $this->spec($count)->should->be(sizeOf(array('A', 'B', 'C')));
    }
}