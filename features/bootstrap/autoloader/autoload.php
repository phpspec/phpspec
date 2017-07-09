<?php
spl_autoload_register(function ($classname) {
    $classname = __DIR__ . '/../src/' . str_replace("\\", "/", trim($classname, "\\")) . ".php";
    if (file_exists($classname)) { include $classname; }
});
if (!class_exists('FakeLoader')) {
    class FakeLoader
    {
        public function getPrefixes()
        {
            return array(
                'Andromeda\\N4S4Arm\\' => array(
                    __DIR__ . '/../src/'
                )
            );
        }

        public function getPrefixesPsr4()
        {
            return array(
                'MilkyWay\\OrionCygnusArm\\' => array(
                    __DIR__ . '/../src/'
                )
            );
        }
    }
}

return new FakeLoader();
