<?php

namespace PhpSpec\Process\Runner;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Process\RerunContext;
use PhpSpec\Runner\ExampleRunner;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class FatalSkippingExampleRunner extends ExampleRunner
{
    /**
     * @var RerunContext
     */
    private $context;

    /**
     * @var ExampleRunner
     */
    private $runner;

    private $dispatcher;

    public function __construct(
        EventDispatcherInterface $dispatcher, PresenterInterface $presenter,
        RerunContext $context, ExampleRunner $runner
    )
    {
        parent::__construct($dispatcher, $presenter);
        $this->context = $context;
        $this->runner = $runner;
        $this->dispatcher = $dispatcher;
    }

    public function run(ExampleNode $example)
    {
        $spec = $example->getFunctionReflection()->getDeclaringClass()->getName();
        $function = $example->getFunctionReflection()->getName();

        if ($this->context->wasFatalSpec($spec, $function)) {

            $event = new ExampleEvent($example);
            $this->dispatcher->dispatch('beforeExample', $event);

            $error = $this->context->getFatalSpecError($spec, $function);
            $failure = new FailureException(
                sprintf(
                    'Fatal error "%s" in "%s" on line %d',
                    $error['message'],
                    $error['file'],
                    $error['line']
                )
            );

            $event = new ExampleEvent($example, null, ExampleEvent::BROKEN, $failure);
            $this->dispatcher->dispatch('afterExample', $event);

            return ExampleEvent::BROKEN;
        }

        return $this->runner->run($example);
    }

}
