<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

// PHP set is taken from the composer `php` constraint (^8.5), so Rector never rewrites to syntax
// below the baseline. This package is 8.5-only, so there is no 8.3/8.4 floor to protect.
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src/Core/src',
        __DIR__ . '/src/Laravel/src',
    ])
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
    )
    ->withImportNames(removeUnusedImports: true);
