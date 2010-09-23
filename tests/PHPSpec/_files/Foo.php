<?php

class Foo {
	public $arg1 = null;
    public function __construct($arg1=null) {
        $this->arg1 = $arg1;
    }
    public function getArg1() {
        return $this->arg1;
    }
}