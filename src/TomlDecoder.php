<?php

namespace Devium\Toml;

use stdClass;

/**
 * @internal
 */
readonly class TomlDecoder
{
    /**
     * @throws TomlError
     */
    public static function decode(string $input, bool $asArray = false): array|stdClass
    {
        $parser = new TomlParser($input);

        if ($asArray) {
            return json_decode(json_encode(TomlNormalizer::normalize($parser->parse())), true);
        }

        return self::toObject(TomlNormalizer::normalize($parser->parse()));
    }

    protected static function toObject(array|TomlObject $arrayObject): array|object
    {
        $return = [];

        foreach ($arrayObject as $key => $value) {
            $return[$key] = $value instanceof TomlObject || is_array($value) ? self::toObject($value) : $value;
        }

        return is_array($arrayObject) ? $return : (object) $return;
    }
}
