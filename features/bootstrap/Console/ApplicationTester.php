<?php

namespace Console;

use PhpSpec\Console\Application;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;
use RuntimeException;

/**
 * A console application tester heavily inspired by/proudly stolen from \Symfony\Component\Console\Tester\ApplicationTester.
 */
class ApplicationTester
{
    /**
     * @var Application $application
     */
    private $application;

    /**
     * @var StringInput $input
     */
    private $input;

    /**
     * @var StreamOutput $output
     */
    private $output;

    /**
     * @var resource $inputStream
     */
    private $inputStream;

    /**
     * @var int $statusCode
     */
    private $statusCode;

    /**
     * @var LoggingReRunner
     */
    private $reRunner;

    /**
     * @param Application $application
     */
    public function __construct(Application $application)
    {
        $this->application = $application;

        $this->reRunner = new LoggingReRunner();
        $this->application->getContainer()->set('process.rerunner.platformspecific', $this->reRunner);
    }

    /**
     * @param string $input
     * @param array  $options
     *
     * @return integer
     */
    public function run($input, array $options = array())
    {
        if (isset($options['interactive']) && $options['interactive']) {
            $this->input = new InteractiveStringInput($input);
        } else {
            $this->input = new StringInput($input);
            $this->input->setInteractive(false);
        }

        $this->output = new StreamOutput(fopen('php://memory', 'w', false));
        if (isset($options['decorated'])) {
            $this->output->setDecorated($options['decorated']);
        }
        if (isset($options['verbosity'])) {
            $this->output->setVerbosity($options['verbosity']);
        }

        $inputStream = $this->getInputStream();
        rewind($inputStream);
        $this->getDialogHelper()->setInputStream($inputStream);

        $this->statusCode = $this->application->run($this->input, $this->output);

        return $this->statusCode;
    }

    /**
     * @param boolean
     *
     * @return string
     */
    public function getDisplay($normalize = false)
    {
        rewind($this->output->getStream());

        $display = stream_get_contents($this->output->getStream());

        if ($normalize) {
            $display = str_replace(PHP_EOL, "\n", $display);
        }

        return $display;
    }

    /**
     * @return InputInterface
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * @return OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * @param string $input
     */
    public function putToInputStream($input)
    {
        fputs($this->getInputStream(), $input);
    }

    /**
     * @return resource
     */
    private function getInputStream()
    {
        if (null === $this->inputStream) {
            $this->inputStream = fopen('php://memory', 'r+', false);
        }

        return $this->inputStream;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @return \Symfony\Component\Console\Helper\DialogHelper
     */
    private function getDialogHelper()
    {
        $dialogHelper = $this->application->getHelperSet()->get('dialog');

        if (!$dialogHelper instanceof DialogHelper) {
            throw new RuntimeException('Cannot get DialogHelper from Application');
        }

        return $dialogHelper;
    }

    /**
     * @return bool
     */
    public function hasBeenRerun()
    {
        return $this->reRunner->hasBeenReRun();
    }
}
