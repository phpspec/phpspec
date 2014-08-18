<?php

namespace PhpSpec\Config;

class OptionsConfig
{
    /**
     * @var bool
     */
    private $stopOnFailureEnabled;

    /**
     * @var bool
     */
    private $codeGenerationEnabled;

    /**
     * @param bool $stopOnFailureEnabled
     * @param bool $codeGenerationEnabled
     */
    public function __construct($stopOnFailureEnabled, $codeGenerationEnabled)
    {
        $this->stopOnFailureEnabled  = $stopOnFailureEnabled;
        $this->codeGenerationEnabled = $codeGenerationEnabled;
    }

    /**
     * @return bool
     */
    public function isStopOnFailureEnabled()
    {
        return $this->stopOnFailureEnabled;
    }

    /**
     * @return bool
     */
    public function isCodeGenerationEnabled()
    {
        return $this->codeGenerationEnabled;
    }
}
