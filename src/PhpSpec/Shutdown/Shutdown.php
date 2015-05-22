<?php

namespace PhpSpec\Shutdown;

use PhpSpec\Formatter\FatalFormatter;
use PhpSpec\Message\Example;

class Shutdown
{
    /**
     * @var Example
     */
    private $message;
  /**
   * @var FatalFormatter
   */
  private $formatter;

  public function __construct(Example $message, FatalFormatter $formatter) {
      register_shutdown_function(array($this, 'updateConsole'));
      $this->message = $message;
      $this->formatter = $formatter;
  }

    public function updateConsole()
    {
      $this->formatter->displayFatal($this->message);
    }
}
