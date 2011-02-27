<?php

if (!defined ('DESCRIBE_BOO')) {
	define('DESCRIBE_BOO', 'describeBoo');
	
	class describeBoo extends PHPSpec_Context {
		public function itShouldBeTrue() {}
		public function itShouldBeFalse() {}
	}
}

