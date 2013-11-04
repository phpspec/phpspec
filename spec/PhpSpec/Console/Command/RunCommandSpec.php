<?php

namespace spec\PhpSpec\Console\Command;

use PhpSpec\Console\Application;
use PhpSpec\ObjectBehavior;
use PhpSpec\Runner\SuiteRunner;
use PhpSpec\ServiceContainer;
use Prophecy\Argument;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommandSpec extends ObjectBehavior
{

    function let(Application $application, HelperSet $helperSet, InputDefinition $definition, ServiceContainer $container)
    {
        $application->getHelperSet()->willReturn($helperSet);
        $application->getDefinition()->willReturn($definition);
        $application->getContainer()->willReturn($container);

        $definition->getArguments()->willReturn(array());
        $definition->getOptions()->willReturn(array());

        $this->setApplication($application);
    }

    function it_will_run_the_suite_correctly(InputInterface $input, OutputInterface $output, $container, SuiteRunner $runner)
    {
        $container->getParam(Argument::cetera())->willReturn();
        $container->get(Argument::cetera())->willReturn();
        $container->setParam(Argument::cetera())->willReturn();
        $container->configure(Argument::cetera())->willReturn();

        $container->get('runner.suite')->willReturn($runner);

        $this->run($input, $output);

        $runner->run(null, null)->shouldHaveBeenCalled();
    }
}
