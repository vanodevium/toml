#!/usr/bin/php

<?php

use Devium\Toml\TomlTag;

include_once './vendor/autoload.php';

try {
    echo json_encode(TomlTag::tagObject(toml_decode(file_get_contents('php://stdin'))));
    exit(0);
} catch (Throwable $e) {
    //exit($e->getMessage()."\n".$e->getTraceAsString());
    exit(1);
}
