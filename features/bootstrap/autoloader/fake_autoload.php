<?php
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
