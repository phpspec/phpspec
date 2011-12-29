<?php
/**
 * PHPSpec
 *
 * LICENSE
 *
 * This file is subject to the GNU Lesser General Public License Version 3
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/lgpl-3.0.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@phpspec.net so we can send you a copy immediately.
 *
 * @category  PHPSpec
 * @package   PHPSpec
 * @copyright Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright Copyright (c) 2010-2012 Pádraic Brady, Travis Swicegood,
 *                                    Marcello Duarte
 * @license   http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
 namespace PHPSpec\Runner;
 
 /**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2012 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class ReporterEvent
{
     /**
      * Event
      *
      * @var string  
      */
      public $event;
     
     /**
      * Example's status
      *
      * @var string
      */
     public $status;
     
     /**
      * Example's name
      *
      * @var string
      */
     public $example;
     
     /**
      * Example's message (if any)
      *
      * @var string
      */
     public $message;
     
     /**
      * Example's backtrace (if any)
      *
      * @var string
      */
     public $backtrace;
     
     /**
      * Example's exception (if any)
      *
      * @var string
      */
     public $exception;
     
     /**
      * Example's time
      *
      * @var float
      */
     public $time;
     
     /**
      * Reporter event is constructed with:
      *
      * @param string     $event
      * @param string     $status
      * @param string     $example
      * @param string     $message (OPTIONAL)
      * @param string     $trace (OPTIONAL)
      * @param \Exception $exception (OPTIONAL)
      */
     public function __construct($event, $status, $example, $message = '',
                                 $trace = '', $e = null, $time = 0.0)
     {
         $this->status    = $status;
         $this->event     = $event;
         $this->example   = $example;
         $this->message   = $message;
         $this->backtrace = $trace;
         $this->exception = $e;
         $this->time      = $time;
     }
     
     /**
      * Reporter event constructor overloaded
      *
      * @param string     $event
      * @param float      $time
      * @param string     $example
      */
     public static function newWithTimeAndName($event, $time, $example)
     {
         return new self($event, '', $example, '', '', null, $time);
     }
}