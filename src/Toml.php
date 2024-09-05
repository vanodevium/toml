<?php

namespace Devium\Toml;

use stdClass;

class Toml
{
    /**
     * @throws TomlError
     */
    public static function encode(array|stdClass $data): string
    {
        return TomlEncoder::encode($data);
    }

    /**
     * @throws TomlError
     */
    public static function decode(string $data, bool $asArray = false, bool $asFloat = false): array|stdClass
    {
        return TomlDecoder::decode($data, $asArray, $asFloat);
    }
}
