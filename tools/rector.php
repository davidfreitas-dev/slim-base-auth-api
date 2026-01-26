<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        dirname(__DIR__) . '/src',
        dirname(__DIR__) . '/tests',
    ]);

    // define sets of rules
    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::TYPE_DECLARATION,
        LevelSetList::UP_TO_PHP_84,
    ]);
};
