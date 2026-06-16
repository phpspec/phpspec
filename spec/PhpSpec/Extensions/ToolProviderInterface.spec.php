<?php

use PhpSpec\Extensions\ToolProviderInterface;

describe(ToolProviderInterface::class, function () {

    it('defines getTools method', function () {
        $ref = new ReflectionClass(ToolProviderInterface::class);

        expect($ref->isInterface())->toBeTrue();
        expect($ref->hasMethod('getTools'))->toBeTrue();
    });
});
