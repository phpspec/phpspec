<?php

set_include_path('.' . PATH_SEPARATOR . dirname(dirname(__FILE__)) .
                       '/src/' . PATH_SEPARATOR .
                       get_include_path());

require_once 'PHPSpec/Framework.php';

define("THIS_REQUIRED_ATTRIBUTE_IS_IGNORED_BY_CONSTRUCTOR", 'ignored');