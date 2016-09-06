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

        $relPath = self::getRelativePath($autoloaderPath, $binPath);

        file_put_contents($binPath, preg_replace(
            "/(COMPOSER_AUTOLOAD = ')[^']*(')/",
            '\1' . $relPath .'\2',
            file_get_contents($binPath)
        ));
    }

    private static function getRelativePath($to, $from)
    {
        for ($i=0; isset($to{$i}); $i++) {
            if (!isset($from{$i}) || $to{$i} != $from{$i}) {
                break;
            }
        }

        return '..' . substr($to, strrpos(substr($to, 0, $i), '/'));
    }
}
