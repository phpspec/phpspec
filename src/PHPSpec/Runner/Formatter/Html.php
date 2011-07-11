<?php

namespace PHPSpec\Runner\Formatter;

require_once 'Text/Template.php';

use \PHPSpec\Util\Backtrace,
    \PHPSpec\Specification\Result\DeliberateFailure;

class Html extends Progress
{
    protected $_result = '';
    protected $_examples = '';
    
    public function output()
    {
        if ($this->justShowAMessage()) {
            return;
        }

        $template = new \Text_Template($this->templateDir() . '/Report.html.dist');
        $template->setVar(array(
            'totals' => $this->getSummary(),
            'results' => $this->getResults()
        ));
        $this->put($template->render());
    }
    
    public function getSummary()
    {
        $template = new \Text_Template($this->templateDir() . '/Totals.html.dist');
        $template->setVar(array(
            'totals' => $this->getTotals(),
            'time' => $this->_reporter->getRuntime()
        ));
        return $template->render();
    }
    
    public function getResults()
    {
        return $this->_result;
    }
    
    
    public function update(\SplSubject $method)
    {
        $args = func_get_args();
        
        switch ($args[1][0]) {
            case 'start' :
                static $groupIndex = 1;
                $template = new \Text_Template($this->templateDir() . '/GroupStart.html.dist');
                $template->setVar(array(
                    'index' => $groupIndex++,
                    'name' => $args[1][2]
                ));
                $this->_result .= $template->render();
                break;
            case 'finish' :
                $template = new \Text_Template($this->templateDir() . '/GroupEnd.html.dist');
                $template->setVar(array(
                    'examples' => $this->_examples
                ));
                $this->_result .= $template->render();
                $this->_examples = '';
                break;
            case 'status' :
                $this->_examples .= $this->specdocx(
                    $args[1][1], $args[1][2],
                    isset($args[1][3]) ? $args[1][3] : '',
                    isset($args[1][4]) ? $args[1][4] : '',
                    isset($args[1][5]) ? $args[1][5] : ''
                );
                break;
            case 'exit':
                $this->output();
                exit;
                break;
        }
    }
    
    protected function specdocx($status, $example, $message = '', $backtrace = '', $e = null)
    {
        switch($status) {
            case '.':
                $template = new \Text_Template($this->templateDir() . '/Passed.html.dist');
                $template->setVar(array(
                    'description' => $example
                ));
                return $template->render();
                break;
            case '*':
                static $pending = 1;
                $template = new \Text_Template($this->templateDir() . '/Pending.html.dist');
                $template->setVar(array(
                    'description' => $example . " (PENDING: $message)",
                    'index' => $pending++
                ));
                return $template->render();
                break;
            case 'E':
                static $error = 1;
                $template = new \Text_Template($this->templateDir() . '/Failed.html.dist');
                $template->setVar(array(
                    'description' => $example . " (ERROR - " . ($error) .")",
                    'message' => $message,
                    'backtrace' => $backtrace,
                    'code' => $this->getCode($e),
                    'index' => $error++
                ));
                return $template->render();
                break;
            case 'F':
                static $failure = 1;
                $template = new \Text_Template($this->templateDir() . '/Failed.html.dist');
                $template->setVar(array(
                    'description' => $example . " (FAILED - " . ($failure) .")",
                    'message' => $message,
                    'backtrace' => $backtrace,
                    'code' => $this->getCode($e),
                    'index' => $failure++
                ));
                return $template->render();
                break;
        }
    }

    protected function templateDir()
    {
        return realpath(dirname(__FILE__)) . '/Html/Template';
    }
    
    public function getCode($e)
    {
        if (!$e instanceof \Exception) {
            return '';
        }
        
        if (!$e instanceof \PHPSpec\Specification\Result\DeliberateFailure) {
            $traceline = Backtrace::getFileAndLine($e->getTrace(), 1);
        } else {
            $traceline = Backtrace::getFileAndLine($e->getTrace());
        }
        $lines = '';
        if (!empty($traceline)) {
            $lines .= $this->getLine($traceline, -2);
            $lines .= $this->getLine($traceline, -1);
            $lines .= $this->getLine($traceline, 0, 'offending');
            $lines .= $this->getLine($traceline, 1);
        }
        
        $template = new \Text_Template($this->templateDir() . '/Code.html.dist');
        $template->setVar(array(
             'code' => $lines
        ));
        return $template->render();
    }
    
    public function getLine($traceline, $relativePosition, $style = 'normal')
    {
        $line = new \Text_Template($this->templateDir() . '/Line.html.dist');
        $code = str_replace(
            array('<span style="color: #0000BB">&lt;?php&nbsp;</span>',
                  '<code>', '</code>'),
            '',
            highlight_string('<?php ' . Backtrace::readLine($traceline['file'],
                             $traceline['line'] + $relativePosition), true)
        );
        $code = preg_replace('/\n/', '', $code);
        $code = preg_replace('/<span style="color: #0000BB">&lt;\?php&nbsp;(.*)(<\/span>+?)/', '$1', $code);
        $line->setVar(array(
            'line' => $traceline['line'] + $relativePosition,
            'class' => $style,
            'code' => ' ' . $code
        ));
        return $line->render();
    }
}