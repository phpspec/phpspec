<?php

if (!defined ('DESCRIBE_BOO')) {
	define('DESCRIBE_BOO', 'describeBoo');
	
	class DescribeBoo extends \PHPSpec\Context {
		public function itShouldBeTrue() {}
		public function itShouldBeFalse() {}
	}
}

