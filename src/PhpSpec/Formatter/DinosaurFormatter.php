<?php

namespace PhpSpec\Formatter;

/**
 * DinosaurFormatter
 * Rawwwr!
 */
class DinosaurFormatter extends NyanFormatter
{
    protected $characterDefinition =
        array(
            array(
                '              ___  ',
                '             / %_) ',
                '     _.----./ /    ',
                '    /        /     ',
                ' __/ (  | (  |     ',
                "/__.-'|_|--|_|     "
            ),
            array(
                '               ___ ',
                '              / %_)',
                '     _.----._/ /   ',
                '    /         /    ',
                ' __/ (  | (  |     ',
                "/__.-|_|--|_|      "
            ),
            array(
                '              ___  ',
                '             / %_) ',
                '    _.----._/ /    ',
                '   /         /     ',
                ' _/ (  / /  /      ',
                "/_/-|_/--|_/       "
            )
        );
}
