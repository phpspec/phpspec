<?php

spl_autoload_register(
    function () {
        foreach (new RecursiveiteratorIterator( new RecursiveDirectoryIterator(__DIR__ . '/..' )) as $fs) {
            if (preg_match('/\\.php$/', $fs->getFilename())) { require_once $fs->getPathName();}
        }
    }
);
