<?php

require_once dirname(__FILE__) . '/Bootstrap.php';

$front = Bootstrap::prepare();
Bootstrap::dispatch($front);