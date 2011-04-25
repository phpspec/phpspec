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
 * @copyright Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                    Marcello Duarte
 * @license   http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
namespace PHPSpec\Runner\Reporter;

/**
 * @see \PHPSpec\Runner\Reporter
 */
use \PHPSpec\Runner\Reporter;

/**
 * @see \Console_Color
 */
require_once 'Console/Color.php';

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Html extends Reporter
{

    /**
     * Whether header has been sent
     * 
     * @var boolean
     */
    protected $_headerSent = false;

    /**
     * Outputs specs
     * 
     * @param unknown_type $specs
     */
    public function output($specs = false)
    {
        echo $this->toString($specs);
    }

    /**
     * Outputs a properly styled status symbol
     * 
     * @param string $symbol
     */
    public function outputStatus($symbol)
    {
        if (!$this->_headerSent) {
            $this->renderHeader();
            echo '<div id="symbols">';
            $this->_headerSent = true;
        }
        switch ($symbol) {
            case '.':
                $symbol = '<span class="passsymbol">.</span>';
                break;
            case 'F':
                $symbol = '<span class="failsymbol">F</span>';
                break;
            case 'E':
                $symbol = '<span class="failsymbol">E</span>';
                break;
            case 'P':
                $symbol = '<span class="pendingsymbol">P</span>';
                break;
            default:
        }
        echo $symbol;
    }

    /**
     * Renders the html report
     * 
     * @see PHPSpec\Runner.Reporter::toString()
     * 
     * @return string
     */
    public function toString($specs = false)
    {
        if ($this->_headerSent) {
            echo '</div>';
            ob_start();
        } else {
            ob_start();
            $this->renderHeader();
        }
        $this->renderSummary();
        if ($specs) {
            $this->renderSpecDocs();
        }
        $this->renderFailures();
        $this->renderErrors();
        $this->renderExceptions();
        $this->renderPending();
        $this->renderFooter();
        return ob_get_clean();
    }

    
    /**
     * Converts object to string by calling toString() and returning the
     * html report
     * @see PHPSpec\Runner.Reporter::__toString()
     * 
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * Formats description
     * 
     * @param string $description
     * @return string
     */
    protected function _format($description)
    {
        $description = preg_replace("/^describe /", '', $description);
        return $description . ' ';
    }

    /**
     * Gets Specdox
     * @see PHPSpec\Runner.Reporter::getSpecdox()
     */
    public function getSpecdox()
    {
    }
    
    /**
     * Renders header
     */
    private function renderHeader()
    {
        ?>
<html>
<head>
    <title>PHPSpec Result</title>
    <style type="text/css">
        body{
            font-size:0.9em;
            font-family:sans-serif;
            background-color:#e7e7e0;
        }
        h2{
            font-size:1.1em;
            text-decoration:underline;
        }
        #summary{
            font-size:1.2em;
            font-weight:bold;
        }
        #summary a{
            color:white;                
        }
        #summary.success{
            background-color:green;
            color:white;
        }
        #summary.failure{
            background-color:red;
            color:white;
        }
        #symbols,#summary,#errors,#specdoc,#failures,#exceptions,#pending {
            margin:4px;
            padding:4px;
        }
        #symbols {
            background-color: #000000;
        }
        #errors,#specdoc,#failures,#exceptions,#pending {                    
            background-color:#fffff7;
            border:1px solid #d5d4c5;
        }                                
        #errors .error{
            cursor:pointer;
        }
        div.exception .context,div.exception .spec {
            cursor:pointer;
        }
        .examples li{
            padding:1px;
        }                
        .examples .Pending .result{
            color:orange;
        }
        .examples .Fail .result, .examples .Error .result,
        .examples .Exception .result {
            color:red;
        }
        .spec .name{
            font-size:1.1em;
            font-weight:bold;
        }
        #specdoc .Error .example, #specdoc .Exception .example,
        #specdoc .Fail .example {
            cursor:pointer;
        }                                                
        .message{
            display:block;
            margin-top:4px;
            font-size:0.8em;
            white-space: pre;
        }
        .passsymbol {
            color: green;
        }
        .failsymbol {
            color: red;
        }
        .pendingsymbol {
            color: yellow;
        }
    </style>            
</head>
<body>
        <?php
    }
    
    /**
     * Renders summary
     */
    private function renderSummary()
    {
        $failureCount = $this->_result->countFailures() +
                         $this->_result->countDeliberateFailures();
        $errorCount = $this->_result->countErrors();
        $exceptionCount = $this->_result->countExceptions();
        $pendingCount = $this->_result->countPending();
        $class = ($errorCount==0 &&
                  $exceptionCount ==0 &&
                  $pendingCount ==0 &&
                  $failureCount==0) ? 'success' : 'failure';
        ?>
        <div id="summary" class="<?php echo $class; ?>">
            <span class="duration">Finished in <?php
            echo $this->_result->getRuntime(); ?> seconds</span>    
            <span class="examples">, <?php
            echo count($this->_result); ?> examples</span>    
            <?php if ($failureCount >0 ) : ?>
            <span class="failures">, <a href="#failures"><?php             
            if ($failureCount == 1) :
                echo $failureCount . ' failure';
            else :
                echo $failureCount . ' failures';
            endif
            ?></a></span>
            <?php endif ?>
            
            <?php if ($errorCount >0 ) : ?>
            <span class="errors">, <a href="#errors"><?php             
            if ($errorCount == 1) :
                echo $errorCount . ' error';
            else :
                echo $errorCount . ' errors';
            endif
            ?></a></span>    
            <?php endif ?>
            
            <?php if ($exceptionCount > 0) : ?>
                <span class="exceptions">,<a href="#exceptions"><?php
                if ($exceptionCount == 1) :
                    echo $exceptionCount . ' exception';
                else :
                    echo $exceptionCount . ' exceptions';
                endif
                ?></a></span>
            <?php endif ?>
            <?php if ($pendingCount > 0) : ?>
            <span class="pending">, <a href="#pending"><?php
            echo $pendingCount; ?> pending</a></span>
            <?php endif ?>
        </div>
        <?php
    }


    /**
     * Renders failures
     */
    private function renderFailures()
    {
        if ($this->_result->countFailures() > 0 ||
            $this->_result->countDeliberateFailures() > 0) :
        ?>
        <div id="failures">
            <h2>Failures</h2>
            <?php            
            $failed = $this->_result->getTypes('fail');
            foreach ($failed as $failure) :
                ?>
                <div class="failure">
                    <span class="context"><?php
                    echo $this->_format($failure->getContextDescription());
                    ?></span>
                    <span class="spec"><?php
                    echo $failure->getSpecificationText(); ?> FAILED</span>
                    <code class="message"><?php
                    echo $failure->getFailedMessage(); ?></code>
                </div>
                <?php                
            endforeach ?>
            <?php            
            $failed = $this->_result->getTypes('deliberateFail');
            foreach ($failed as $failure) :
                ?>
                <div class="failure">
                    <span class="context"><?php
            echo $this->_format($failure->getContextDescription()); ?></span>
                    <span class="spec"><?php
            echo $failure->getSpecificationText(); ?> FAILED</span>
                    <code class="message"><?php
            echo $failure->getMessage(); ?></code>                    
                </div>
                <?php                
            endforeach ?>
        </div>
        <?php
        endif;
    }

    /**
     * Renders errors
     */
    private function renderErrors()
    {
        if ($this->_result->countErrors() > 0) :
        ?>
        <div id="errors">
            <h2>Errors</h2>
            <?php            
            $errors = $this->_result->getTypes('error');
            foreach ($errors as $error) :
                ?>
                <div class="error">
                    <span class="context"><?php
            echo $this->_format($error->getContextDescription()); ?></span>
                    <span class="spec"><?php
            echo $error->getSpecificationText(); ?> ERROR</span>
                    <code class="message"><?php
            echo $error->toString(); ?></code>                    
                </div>
                <?php                
            endforeach ?>            
        </div>
        <?php
        endif;
    }

    /**
     * Enter description here ...
     */
    private function renderExceptions()
    {
        if ($this->_result->countExceptions() > 0) :
        ?>
        <div id="exceptions">
            <h2>Exceptions</h2>
            <?php            
            $exceptions = $this->_result->getTypes('exception');
            foreach ($exceptions as $exception) :
                ?>
                <div class="exception">
                    <span class="context"><?php
            echo $this->_format($exception->getContextDescription()); ?></span>
                    <span class="spec"><?php
            echo $exception->getSpecificationText(); ?> EXCEPTION</span>
                    <code class="message"><?php
            echo $exception->toString(); ?></code>                    
                </div>
                <?php                
            endforeach ?>
        </div>
        <?php
        endif;
    }

    /**
     * Enter description here ...
     */
    private function renderPending()
    {
        if ($this->_result->countPending() > 0) :
        ?>
        <div id="pending">
            <h2>Pending</h2>
            <?php            
            $pendings = $this->_result->getTypes('pending');
            foreach ($pendings as $pending) :
                ?>
                <div class="exception">
                    <span class="context"><?php
            echo $this->_format($pending->getContextDescription()); ?></span>
                    <span class="spec"><?php
            echo $pending->getSpecificationText(); ?> PENDING</span>
                    <code class="message"><?php
            echo $pending->getMessage(); ?></code>                    
                </div>
                <?php                
            endforeach ?>            
        </div>
        <?php
        endif;
    }

    /**
     * Enter description here ...
     */
    function renderSpecDocs()
    {

        $examples = $this->_result->getExamples();
        $contexts = array();
        foreach ($examples as $example) {
            if (!isset($contexts[$example->getContextDescription()])) {
                $contexts[$example->getContextDescription()] = array();
            }
            $contexts[$example->getContextDescription()][] = $example;
        }
        ?>
        <div id="specdoc">
        <h2>Specifications</h2>
        <?php
        foreach ($contexts as $description=>$arrayOfExamples) :
            ?>
            <div class="spec">
                <span class="name"><?php
                echo $this->_format($description) ?></span>
                <div class="examples">
                <ul>
                <?php
                foreach($arrayOfExamples as $example) :                    
                    $class = get_class($example);
                    $parts = explode('_', $class);
                    $type = array_pop($parts);                    
                    ?>
                    <li class="<?php echo $type ?>">
                        <span class="example"><?php
                        echo $example->getSpecificationText() ?>
                        </span>
                        <?php if ($type !='Pass') : ?>
                            <span class="result"><?php
                            echo strtoupper($type) ?></span>
                        <?php endif ?>
                         <?php
                         switch ($type) :
                             case 'Fail':
                             ?><code class="message"><?php
                             echo $example->getFailedMessage(); ?></code><?php
                                 break;

                             case 'Error':
                             case 'Exception':
                             ?><code class="message"><?php
                             echo $example->getException(); ?></code><?php
                             default:
                                 break;
                         endswitch ?>                             
                    </li>
                    <?php    
                endforeach ?>
                </ul>
                </div>
            </div>
            <?php            
        endforeach
        ?>            
        </div>
        <?php
    }

    /**
     * Enter description here ...
     */
    private function renderFooter()
    {
        ?>
        <script type="text/javascript">
        
        // make all code tags under the given container id hidden by default,
        // and toggleable by clicking on example name.
        function initCodeBlocks(id){
            var div = document.getElementById(id);
            if (div) {
                var messages = div.getElementsByTagName('code');
                for (var i = 0; i < messages.length; i++) {
                    messages[i].style.display='none';
                }
                var errors = div.getElementsByTagName('div');
                for (var i = 0; i < errors.length; i++) {
                    var spans = errors[i].getElementsByTagName('span');
                    for (var j = 0; j < spans.length; j++) {
                        if (spans[j].getAttribute('class') == 'context' ||
                            spans[j].getAttribute('class') == 'spec') {
                            spans[j].onclick = function() {
                                var p = this.parentNode;
                                var messages = p.getElementsByTagName('code');
                                for (var m = 0; m < messages.length; m++) {
                                    if (messages[m].style.display == 'none') {
                                        messages[m].style.display = 'block';
                                    } else {
                                        messages[m].style.display = 'none';
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // make specdoc code blocks hidden by default,
        // and toggleable by clicking on example name. 
        var specdoc_div = document.getElementById('specdoc');
        if (specdoc_div) {
            var messages = specdoc_div.getElementsByTagName('code');
            for(var i = 0; i < messages.length; i++) {
                messages[i].style.display = 'none';
            }
            var li = specdoc_div.getElementsByTagName('li');
            for (var i = 0; i < li.length; i++) {
                if (li[i].getAttribute('class') == 'Fail' ||
                   li[i].getAttribute('class') == 'Error' ||
                   li[i].getAttribute('class') == 'Exception') {
                    var spans = li[i].getElementsByTagName('span');
                    for (var k=0; k<spans.length; k++) {
                        spans[k].onclick = function() {
                            var p = this.parentNode;
                            var messages = p.getElementsByTagName('code');
                            for (var j = 0; j < messages.length; j++) {
                                if(messages[j].style.display == 'none'){
                                    messages[j].style.display = 'block';
                                }else{
                                    messages[j].style.display = 'none';
                                }
                            }
                        }
                    }
                }
            }
        }

        initCodeBlocks('errors');
        initCodeBlocks('exceptions');
        </script>
        </body>
        </html>
        <?php
    }

}
