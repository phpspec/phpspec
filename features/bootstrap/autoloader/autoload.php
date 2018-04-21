<?php
spl_autoload_register(function ($classname) {
    $classname = __DIR__ . '/../src/' . str_replace("\\", "/", trim($classname, "\\")) . ".php";
    if (file_exists($classname)) { include $classname; }
});
if (class_exists('FakeLoader')) {
    return new FakeLoader();
}
