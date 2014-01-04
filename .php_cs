<?php

$finder = Symfony\CS\Finder\DefaultFinder::create()
    ->exclude('spec')
    ->exclude('vendor')
    ->notName('SpecificationGenerator.php') // contains spec example
    ->in(__DIR__);

return Symfony\CS\Config\Config::create()
    ->finder($finder);
