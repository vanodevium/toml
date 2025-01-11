#!/usr/bin/php

<?php

use Devium\Toml\TomlTag;

include_once './vendor/autoload.php';

try {
    echo json_encode(TomlTag::tagObject(toml_decode(file_get_contents('php://stdin'))), JSON_THROW_ON_ERROR);
    exit(0);
} catch (Throwable $e) {
    exit(1);
}
