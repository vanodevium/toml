<?php

namespace Devium\Toml;

use Stringable;

/**
 * @internal
 */
abstract class TomlAbstractDateTime implements Stringable
{
    protected static function isYear(int $value): bool
    {
        return $value >= 0 && $value <= 9999;
    }

    protected static function isMonth(int $value): bool
    {
        return $value > 0 && $value <= 12;
    }

    protected static function isDay(int $value): bool
    {
        return $value > 0 && $value <= 31;
    }

    protected static function isHour(int $value): bool
    {
        return $value >= 0 && $value < 24;
    }

    protected static function isMinute(int $value): bool
    {
        return $value >= 0 && $value < 60;
    }

    protected static function isSecond(int $value): bool
    {
        return $value >= 0 && $value < 60;
    }

    abstract public function __toString(): string;

    protected function zeroPad(int $int, int $len = 2): string
    {
        return str_pad((string) $int, $len, '0', STR_PAD_LEFT);
    }
}
