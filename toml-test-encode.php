#!/usr/bin/php

<?php

use Devium\Toml\TomlTag;

include_once './vendor/autoload.php';

try {
    echo toml_encode(TomlTag::untagObject(json_decode(file_get_contents('php://stdin'), false, 512, JSON_THROW_ON_ERROR)));
    exit(0);
} catch (Throwable $e) {
    exit(1);
}
