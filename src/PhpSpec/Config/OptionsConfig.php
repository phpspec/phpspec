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
     * @var bool
     */
    private $reRunEnabled;

    /**
     * @param bool $stopOnFailureEnabled
     * @param bool $codeGenerationEnabled
     * @param bool $reRunEnabled
     */
    public function __construct($stopOnFailureEnabled, $codeGenerationEnabled, $reRunEnabled)
    {
        $this->stopOnFailureEnabled  = $stopOnFailureEnabled;
        $this->codeGenerationEnabled = $codeGenerationEnabled;
        $this->reRunEnabled = $reRunEnabled;
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

    public function isReRunEnabled()
    {
        return $this->reRunEnabled;
    }
}
