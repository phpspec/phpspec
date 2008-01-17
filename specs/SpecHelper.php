<?php

set_include_path(
    '.' . PATH_SEPARATOR
    . dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'src' . PATH_SEPARATOR
    . get_include_path()
);