<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withDowngradeSets(php80: true)
    ->withFluentCallNewLine()
    ->withImportNames(
        importNames: false,
        importDocBlockNames: false,
        importShortClasses: false,
        removeUnusedImports: true,
    )
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
;
