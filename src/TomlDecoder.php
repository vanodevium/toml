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
        $normalized = TomlNormalizer::normalize((new TomlParser($input, $asFloat))->parse());

        return $asArray ? self::toArray($normalized) : self::toObject($normalized);
    }

    protected static function toArray(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface || $value instanceof TomlInternalDateTime) {
            return $value;
        }

        if (is_array($value) || is_object($value)) {
            $array = [];
            foreach ((array) $value as $key => $item) {
                $array[$key] = self::toArray($item);
            }

            return $array;
        }

        return $value;
    }

    protected static function toObject(array|TomlObject $value): array|object
    {
        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = $item instanceof TomlObject || is_array($item) ? self::toObject($item) : $item;
        }

        return is_array($value) ? $result : (object) $result;
    }
}
