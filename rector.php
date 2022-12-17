<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php71\Rector\FuncCall\CountOnNullRector;
use Rector\Php80\Rector\FuncCall\TokenGetAllToObjectRector;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([LevelSetList::UP_TO_PHP_80]);
    $rectorConfig->skip([
        TokenGetAllToObjectRector::class, # causing rector to crash
        CountOnNullRector::class, #needs manual review
    ]);

    $rectorConfig->rule();

    $rectorConfig->paths([__DIR__ . '/src']);
};
