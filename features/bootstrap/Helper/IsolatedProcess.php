<?php

namespace Helper;

class IsolatedProcess
{
    /**
     * @var string
     */
    private $command;

    /**
     * @var array
     */
    private $env;

    /**
     * @var string
     */
    private $cwd;

    /**
     * @var array
     */
    private $descriptors = array(
        array('pipe', 'r'),
        array('pipe', 'w'),
        array('pipe', 'w')
    );

    /**
     * @var resource
     */
    private $process;

    /**
     * @var array
     */
    private $processPipes;

    /**
     * @var string
     */
    private $stdOut;

    /**
     * @var string
     */
    private $stdErr;

    /**
     * @param string $command
     * @param array $env
     */
    public function __construct($command, array $env = array(), $cwd = null)
    {
        $this->command = $command;
        $this->env = $env;
        $this->cwd = $cwd;
    }

    public function open()
    {
        $this->process = proc_open(
            $this->command,
            $this->descriptors,
            $this->processPipes,
            is_null($this->cwd) ? getcwd() : $this->cwd,
            $this->env
        );
    }

    /**
     * @return int
     */
    public function close()
    {
        fclose($this->processPipes[0]);

        $this->stdOut = stream_get_contents($this->processPipes[1]);
        $this->stdErr = stream_get_contents($this->processPipes[2]);

        return proc_close($this->process);
    }

    /**
     * @return int
     */
    public function run()
    {
        $this->open();

        return $this->close();
    }

    /**
     * @param string $input
     */
    public function sendInput($input)
    {
        fwrite($this->processPipes[0], $input);
    }

    /**
     * @return string
     */
    public function getOutput()
    {
        return $this->stdOut;
    }

    /**
     * @return string
     */
    public function getError()
    {
        return $this->stdErr;
    }
}
