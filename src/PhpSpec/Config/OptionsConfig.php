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
     * @var bool
     */
    private $fakingEnabled;

    /**
     * @param bool $stopOnFailureEnabled
     * @param bool $codeGenerationEnabled
     * @param bool $reRunEnabled
     * @param bool $fakingEnabled
     */
    public function __construct($stopOnFailureEnabled, $codeGenerationEnabled, $reRunEnabled, $fakingEnabled)
    {
        $this->stopOnFailureEnabled  = $stopOnFailureEnabled;
        $this->codeGenerationEnabled = $codeGenerationEnabled;
        $this->reRunEnabled = $reRunEnabled;
        $this->fakingEnabled = $fakingEnabled;
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

    public function isFakingEnabled()
    {
        return $this->fakingEnabled;
    }
}
