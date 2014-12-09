<?php

namespace PhpSpec\Process;

class RerunContext
{
    const ENV_NAME = 'RERUN_CONTEXT';

    private $fatals = array();

    public static function fromString($string)
    {
        $data = json_decode($string, true);

        $rerunContext = new RerunContext();
        $rerunContext->fatals = $data['fatals'];

        return $rerunContext;
    }

    public function setFatalSpec($spec, $example, $error)
    {
        $this->fatals[$spec][$example] = $error;
    }

    public function wasFatalSpec($spec, $example)
    {
        return array_key_exists($spec, $this->fatals)
            && array_key_exists($example, $this->fatals[$spec]);
    }

    public function getFatalSpecError($spec, $example)
    {
        return $this->fatals[$spec][$example];
    }

    public function asString()
    {
        return json_encode(array('fatals' => $this->fatals));
    }

    public function listFatalSpecs()
    {
        $fatalSpecs = array();

        foreach ($this->fatals as $spec => $examples) {
            foreach ($examples as $example => $error) {
                $fatalSpecs[] = array($spec, $example);
            }
        }

        return $fatalSpecs;
    }
}
