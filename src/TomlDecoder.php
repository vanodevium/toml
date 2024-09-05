<?php

namespace Devium\Toml;

use DateTimeInterface;
use stdClass;

/**
 * @internal
 */
class TomlDecoder
{
    /**
     * @throws TomlError
     */
    public static function decode(string $input, bool $asArray = false, bool $asFloat = false): array|stdClass
    {
        $parser = new TomlParser($input, $asFloat);

        if ($asArray) {
            return self::toArray(TomlNormalizer::normalize($parser->parse()));
        }

        return self::toObject(TomlNormalizer::normalize($parser->parse()));
    }

    protected static function toArray(mixed $object): mixed
    {
        if ($object instanceof DateTimeInterface) {
            return $object;
        }

        if ($object instanceof TomlInternalDateTime) {
            return $object;
        }

        if (is_array($object) || is_object($object)) {
            $return = [];
            foreach ((array) $object as $key => $value) {
                $return[$key] = self::toArray($value);
            }

            return $return;
        }

        return $object;
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
