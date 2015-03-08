<?php

spl_autoload_register(
    function () {
        foreach (new RecursiveiteratorIterator( new RecursiveDirectoryIterator(__DIR__ . '/..' )) as $fs) {
            if ($fs->getExtension() == 'php') { require_once $fs->getPathName();}
        }
    }
);
