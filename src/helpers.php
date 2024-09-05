<?php

use Devium\Toml\Toml;
use Devium\Toml\TomlError;

if (! function_exists('toml_encode')) {
    /**
     * @throws TomlError
     */
    function toml_encode(array|stdClass $toml): string
    {
        return Toml::encode($toml);
    }
}

if (! function_exists('toml_decode')) {
    /**
     * @throws TomlError
     */
    function toml_decode(string $toml, bool $asArray = false, bool $asFloat = false): array|stdClass
    {
        return Toml::decode($toml, $asArray, $asFloat);
    }
}
