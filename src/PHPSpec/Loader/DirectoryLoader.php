<?php

namespace PHPSpec\Loader;

class DirectoryLoader extends ClassLoader
{
    
    public function load($specDir, $ignore = array())
    {
        $ignore = $this->lookForIgnoreConfig($specDir, $ignore);
        $directory = new \DirectoryIterator($specDir);
        $loaded = array();
        
        foreach ($directory as $file) {
            if ($file->isDot()) {
                continue;
            }
            
            if ($this->fileIsNotInIgnoreList($file, $ignore)) {
                if ($file->isDir()) {
                    $loaded = array_merge($loaded, $this->load($file->getRealpath(), $ignore));
                } else {
                    $example = parent::load($file->getRealpath());
                    if ($example !== false && $example !== array(false)) {
                        if (!is_array($example)) {
                            $example = array($example);
                        }
                        $loaded = array_merge($loaded, $example);
                    }
                }
            }
        }

        return $loaded;
    }
    
    private function fileIsNotInIgnoreList($file, $ignore)
    {
        return !in_array($file->getRealpath(), $ignore);
    }
    
    private function lookForIgnoreConfig($specDir, $ignore = array())
    {
        if (empty($ignore) && file_exists($specDir . '/.specignore')) {
            $ignore = array_merge($ignore, file($specDir . '/.specignore'));
            $cwd = getcwd();
            chdir($specDir);
            $ignore = array_map('realpath', $ignore);
            chdir($cwd);
        }
        return $ignore;
    }
}