<?php

require_once 'Console/Color.php';

class PHPSpec_Runner_Reporter_Html extends PHPSpec_Runner_Reporter {

    protected $_headerSent = false;

	public function output($specs = false)
	{
		echo $this->toString($specs);
	}

	/**
     * Not needed for the HTML interface
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
		if($specs){
			$this->renderSpecDocs();
		}
		$this->renderFailures();
		$this->renderErrors();
		$this->renderExceptions();
		$this->renderPending();
		$this->renderFooter();
		return ob_get_clean();
	}

	
	public function __toString()
	{
		return $this->toString();
	}

	protected function _format($description)
	{
		$description = preg_replace("/^describe /", '', $description);
		return $description . ' ';
	}

	public function getSpecdox(){		
	}
	
	private function renderHeader(){
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
				#symbols,#summary,#errors,#specdoc,#failures,#exceptions,#pending{
					margin:4px;
					padding:4px;
				}
                #symbols {
                    background-color: #000000;
                }
				#errors,#specdoc,#failures,#exceptions,#pending{					
					background-color:#fffff7;
					border:1px solid #d5d4c5;
				}								
				#errors .error{
					cursor:pointer;
				}
				div.exception .context,div.exception .spec{
					cursor:pointer;
				}
				.examples li{
					padding:1px;
				}				
				.examples .Pending .result{
					color:orange;
				}
				.examples .Fail .result, .examples .Error .result, .examples .Exception .result{
					color:red;
				}
				.spec .name{
					font-size:1.1em;
					font-weight:bold;
				}
				#specdoc .Error .example, #specdoc .Exception .example, #specdoc .Fail .example{
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
	
	private function renderSummary(){
		$failure_count = $this->_result->countFailures() + $this->_result->countDeliberateFailures();
		$error_count = $this->_result->countErrors();
		$exception_count = $this->_result->countExceptions();
		$pending_count = $this->_result->countPending();
		$class = ($error_count==0 && $exception_count ==0 && $pending_count ==0 && $failure_count==0) ? 'success' : 'failure';
		?>
		<div id="summary" class="<?php echo $class; ?>">
			<span class="duration">Finished in <?php echo $this->_result->getRuntime(); ?> seconds</span>	
			<span class="examples">, <?php echo count($this->_result); ?> examples</span>	
			<?php if ($failure_count >0 ) { ?>
			<span class="failures">, <a href="#failures"><?php 			
			if ($failure_count == 1) {
				echo $failure_count . ' failure';
			} else {
				echo $failure_count . ' failures';
			}
			?></a></span>	
			<?php } ?>
			
			<?php if ($error_count >0 ) { ?>
			<span class="errors">, <a href="#errors"><?php 			
			if ($error_count == 1) {
				echo $error_count . ' error';
			} else {
				echo $error_count . ' errors';
			}
			?></a></span>	
			<?php } ?>
			
			<?php if ($exception_count > 0) { ?>
				<span class="exceptions">,<a href="#exceptions"><?php				
				if ($exception_count == 1) {
					echo $exception_count . ' exception';
				} else {
					echo $exception_count . ' exceptions';
				}
				?></a></span><?php
			}?>
			<?php if ($pending_count > 0) { ?>
			<span class="pending">, <a href="#pending"><?php echo $pending_count; ?> pending</a></span>
			<?php } ?>			
		</div>
		<?php
	}


	private function renderFailures(){
		if ($this->_result->countFailures() > 0 || $this->_result->countDeliberateFailures() > 0) {
		?>
		<div id="failures">
			<h2>Failures</h2>
			<?php			
			$failed = $this->_result->getTypes('fail');
			foreach ($failed as $failure) {
				?>
				<div class="failure">
					<span class="context"><?php echo $this->_format($failure->getContextDescription()); ?></span>
					<span class="spec"><?php echo $failure->getSpecificationText(); ?> FAILED</span>
					<code class="message"><?php echo $failure->getFailedMessage(); ?></code>					
				</div>
				<?php				
			}?>
            <?php			
			$failed = $this->_result->getTypes('deliberateFail');
			foreach ($failed as $failure) {
				?>
				<div class="failure">
					<span class="context"><?php echo $this->_format($failure->getContextDescription()); ?></span>
					<span class="spec"><?php echo $failure->getSpecificationText(); ?> FAILED</span>
					<code class="message"><?php echo $failure->getMessage(); ?></code>					
				</div>
				<?php				
			}?>
		</div>
		<?php
		}
	}

	private function renderErrors(){
		if ($this->_result->countErrors() > 0) {
		?>
		<div id="errors">
			<h2>Errors</h2>
			<?php			
			$errors = $this->_result->getTypes('error');
			foreach ($errors as $error) {
				?>
				<div class="error">
					<span class="context"><?php echo $this->_format($error->getContextDescription()); ?></span>
					<span class="spec"><?php echo $error->getSpecificationText(); ?> ERROR</span>
					<code class="message"><?php echo $error->toString(); ?></code>					
				</div>
				<?php				
			}?>			
		</div>
		<?php
		}
	}

	private function renderExceptions(){
		if ($this->_result->countExceptions() > 0) {
		?>
		<div id="exceptions">
			<h2>Exceptions</h2>
			<?php			
			$exceptions = $this->_result->getTypes('exception');
			foreach ($exceptions as $exception) {
				?>
				<div class="exception">
					<span class="context"><?php echo $this->_format($exception->getContextDescription()); ?></span>
					<span class="spec"><?php echo $exception->getSpecificationText(); ?> EXCEPTION</span>
					<code class="message"><?php echo $exception->toString(); ?></code>					
				</div>
				<?php				
			}?>			
		</div>
		<?php
		}
	}

	private function renderPending(){
		if ($this->_result->countPending() > 0) {
		?>
		<div id="pending">
			<h2>Pending</h2>
			<?php			
			$pendings = $this->_result->getTypes('pending');
			foreach ($pendings as $pending) {
				?>
				<div class="exception">
					<span class="context"><?php echo $this->_format($pending->getContextDescription()); ?></span>
					<span class="spec"><?php echo $pending->getSpecificationText(); ?> PENDING</span>
					<code class="message"><?php echo $pending->getMessage(); ?></code>					
				</div>
				<?php				
			}?>			
		</div>
		<?php
		}
	}

	function renderSpecDocs(){

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
		foreach ($contexts as $description=>$arrayOfExamples) {
			?>
			<div class="spec">
				<span class="name"><?php echo $this->_format($description); ?></span>
				<span class="examples">
				<ul>
				<?php
				foreach($arrayOfExamples as $example) {					
					$class = get_class($example);
					$parts = explode('_', $class);
					$type = array_pop($parts);					
					?>
					<li class="<?php echo $type; ?>">
						<span class="example"><?php echo $example->getSpecificationText(); ?>							
						</span>					 						 		
						<?php if($type !='Pass'){ ?>
							<span class="result"><?php echo strtoupper($type); ?></span>								
						<?php } ?>
				 		<?php
				 		switch ($type) {
				 			case 'Fail':
				 				?><code class="message"><?php echo $example->getFailedMessage(); ?></code><?php
				 				break;

				 			case 'Error':
				 			case 'Exception':
				 				?><code class="message"><?php echo $example->getException(); ?></code><?php
				 			default:
				 				break;
				 		}?>					 		
					</li>
					<?php	
				}?>
				</ul>
				</span>
			</div>
			<?php			
		}
		?>			
		</div>
		<?php
	}

	private function renderFooter(){
		?>
		<script type="text/javascript">
		
		// make all code tags under the given container id hidden by default, and toggleable by clicking on example name.
		function initCodeBlocks(id){
			var div = document.getElementById(id);
			if(div){
				var error_messages = div.getElementsByTagName('code');
				for(var i=0; i<error_messages.length; i++){
					error_messages[i].style.display='none';
				}
				var errors = div.getElementsByTagName('div');
				for(var i=0; i<errors.length; i++){
					var spans = errors[i].getElementsByTagName('span');
					for(var j=0; j<spans.length; j++){
						if(spans[j].getAttribute('class')=='context' || spans[j].getAttribute('class')=='spec'){
							spans[j].onclick = function(){

								var error_messages = this.parentNode.getElementsByTagName('code');
								for(var m=0; m<error_messages.length; m++){
									if(error_messages[m].style.display=='none'){
										error_messages[m].style.display='block';
									}else{
										error_messages[m].style.display='none';
									}
								}

							}
						}
					}
				}
			}
		}

		// make specdoc code blocks hidden by default, and toggleable by clicking on example name. 
		var specdoc_div = document.getElementById('specdoc');
		if(specdoc_div){
			var error_messages = specdoc_div.getElementsByTagName('code');
			for(var i=0; i<error_messages.length; i++){
				error_messages[i].style.display='none';
			}
			var li = specdoc_div.getElementsByTagName('li');
			for(var i=0; i<li.length; i++){
				if(li[i].getAttribute('class')=='Fail' || li[i].getAttribute('class')=='Error' || li[i].getAttribute('class')=='Exception'){
					var spans = li[i].getElementsByTagName('span');
					for(var k=0; k<spans.length; k++){
						spans[k].onclick = function(){
							var error_messages = this.parentNode.getElementsByTagName('code');
							for(var j=0; j<error_messages.length; j++){
								if(error_messages[j].style.display=='none'){
									error_messages[j].style.display='block';
								}else{
									error_messages[j].style.display='none';
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
