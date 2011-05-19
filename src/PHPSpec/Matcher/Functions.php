<?php

namespace PHPSpec\Matcher;

function define($matcherName, $matcherDefinition)
{
    \PHPSpec\Matcher\MatcherRepository::add($matcherName, $matcherDefinition);
}