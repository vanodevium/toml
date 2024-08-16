<?php

namespace Devium\Toml;

use DateTimeInterface;
use DateTimeZone;
use Stringable;

/**
 * @internal
 */
abstract class TomlInternalDateTime implements Stringable, TomlDateTimeInterface
{
    public static function toTOMLString(DateTimeInterface $dateTime): string
    {
        $ms = $dateTime->format('u');

        return str_replace(
            '~',
            substr($ms, 0, 3),
            $dateTime
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s.~p'));
    }

    public static function isValidFebruary(int $year, int $month, int $day): bool
    {
        if ($month !== 2) {
            return true;
        }

        return $day <= (self::isLeap($year) ? 29 : 28);
    }

    protected static function isLeap(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;
    }

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
