<?php

namespace PhpSpec\Process\Shutdown;

use PhpSpec\Formatter\FatalFormatter;
use PhpSpec\Message\MessageInterface;

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

  public function __construct(MessageInterface $message, FatalFormatter $formatter)
  {
      $this->message = $message;
      $this->formatter = $formatter;
  }

  public function registerShutdown()
  {
      ini_set('display_errors', '0');
      error_reporting(E_NOTICE);
      register_shutdown_function(array($this, 'updateConsole'));
      return true;
  }

  public function updateConsole()
  {
      $this->formatter->displayFatal($this->message);
  }
}
