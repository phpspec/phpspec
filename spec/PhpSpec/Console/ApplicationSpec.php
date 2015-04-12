<?php

namespace spec\PhpSpec\Console;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ApplicationSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith('test');
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('PhpSpec\Console\Application');
    }

    function it_appends_config_dir_to_suite()
    {
        $config = array('suites' => array());
        $config['suites']['andromeda_suite'] = array('namespace' => 'Andromeda');
        $configDir = 'folder/to/Config';

        $configResult = $this->appendConfigDirToSuite($configDir, $config);
        $configResult['suites']['andromeda_suite']['config_dir']->shouldBe($configDir);
    }
}
