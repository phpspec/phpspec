<?php

use PhpSpec\OwnCode;
use PhpSpec\ProjectRoot;

describe(OwnCode::class, function () {

    it('holds a file under the project and outside every vendor directory', function () {
        $own = new OwnCode(ProjectRoot::at('/proj'), [ProjectRoot::at('/proj/vendor'), ProjectRoot::at('/proj/tools/vendor')]);

        expect($own->holds('/proj/src/App/Basket.php'))->toBeTrue();
        expect($own->holds('/proj/vendor/acme/lib/Old.php'))->toBeFalse();
        expect($own->holds('/proj/tools/vendor/acme/lib/Old.php'))->toBeFalse();
        expect($own->holds('/elsewhere/acme/lib/Old.php'))->toBeFalse();
    });

    it('takes the directory it runs in for the project, and the vendor directory of every Composer autoloader for the libraries', function () {
        $own = OwnCode::here();

        expect($own->holds(getcwd() . '/src/PhpSpec/OwnCode.php'))->toBeTrue();
        expect($own->holds(getcwd() . '/vendor/symfony/console/Application.php'))->toBeFalse();
    });
});
