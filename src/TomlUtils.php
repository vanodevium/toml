<?php

namespace Devium\Toml;

/**
 * @internal
 */
final class TomlUtils
{
    public static function isDecimal($char): bool
    {
        return $char >= '0' && $char <= '9';
    }

    public static function isHexadecimal($char): bool
    {
        return ($char >= 'A' && $char <= 'Z') || ($char >= 'a' && $char <= 'z') || ($char >= '0' && $char <= '9');
    }

    public static function isUnicode($char): bool
    {
        return ($char >= 'A' && $char <= 'F') || ($char >= 'a' && $char <= 'f') || ($char >= '0' && $char <= '9');
    }

    public static function isOctal($char): bool
    {
        return $char >= '0' && $char <= '7';
    }

    public static function isBinary($char): bool
    {
        return $char === '0' || $char === '1';
    }

    public static function stringSlice(string $str, int $start, int $end = 0): string
    {
        if ($end === 0) {
            return substr($str, $start);
        }

        $end -= $start;

        return substr($str, $start, $end);
    }
}
