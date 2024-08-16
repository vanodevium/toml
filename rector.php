<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Exception\Configuration\InvalidConfigurationException;

try {
    return RectorConfig::configure()
        ->withPaths([
            __DIR__.'/src',
            __DIR__.'/tests',
        ])
        ->withPreparedSets(
            codeQuality: true,
        )
        ->withPhpSets(php81: true);
} catch (InvalidConfigurationException $e) {

}
