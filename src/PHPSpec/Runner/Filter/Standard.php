<?php

class PHPSpec_Runner_Filter_Standard extends FilterIterator
{

    public function accept()
    {
        $path = $this->getInnerIterator()->current();
        $fileName = basename($path);
        $filePrefix = substr($fileName, 0, 8);
        $fileSuffix = substr($fileName, -4, 4);
        if (($filePrefix == 'Describe' || $filePrefix == 'describe') && $fileSuffix == '.php') {
            return true;
        }
        return false;
    }

}