<?php

spl_autoload_register(
    function () {
        $fs = new CallbackFilterIterator(
            new RecursiveiteratorIterator(
                new RecursiveDirectoryIterator(__DIR__ . '/..')
            ),
            function (SplFileInfo $fileInfo) {
                return $fileInfo->getExtension() == 'php';
            }
        );

        foreach ($fs as $f) {
            require_once $f->getPathName();
        }
    }
);
