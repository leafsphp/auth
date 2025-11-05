<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withDowngradeSets(false, false, false, false, false, true)
    ->withFluentCallNewLine()
    ->withImportNames(true, true, true, true)
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
;
