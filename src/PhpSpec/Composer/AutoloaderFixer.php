<?php

namespace PhpSpec\Composer;

use Composer\Script\Event;
use Composer\Installer\PackageEvent;

class AutoloaderFixer
{
    public static function postUpdate(Event $event)
    {
        $autoloaderPath = $event->getComposer()->getConfig()->get('vendor-dir') . '/autoload.php';
        $binPath = $event->getComposer()->getConfig()->get('bin-dir') . '/phpspec';

        $binary = file_get_contents($binPath );

        $newBinary = preg_replace(
            "/(COMPOSER_AUTOLOAD=')[^']*(')/",
            '\1' . $autoloaderPath .'\2',
            $binary
        );

        file_put_contents($binPath, $newBinary);
    }
}