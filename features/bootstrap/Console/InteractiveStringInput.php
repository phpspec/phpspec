<?php

use Symfony\Component\Console\Input\StringInput;

class InteractiveStringInput extends StringInput
{
    public function setInteractive($interactive)
    {
        // this function is disabled to prevent setting non interactive mode on string input after posix_isatty return false
    }
}
