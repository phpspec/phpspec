<?php

set_include_path(
    dirname(__FILE__) . '/../src' . PATH_SEPARATOR .
    dirname(__FILE__) . '/Runner/Loader/Classname' . PATH_SEPARATOR .
    get_include_path()
);

require_once 'PHPSpec/Framework.php';
