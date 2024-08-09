<?php

namespace Devium\Toml;

final class TomlLocalDate extends TomlInternalDateTime
{
    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly int $day,
    ) {}

    /**
     * @throws TomlError
     */
    public static function fromString($value): self
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
            throw new TomlError("invalid local date format \"$value\"");
        }

        [$year, $month, $day] = array_map('intval', explode('-', (string) $value));

        if (! self::isYear($year) || ! self::isMonth($month) || ! self::isDay($day)) {
            throw new TomlError("invalid local date format \"$value\"");
        }

        if (! self::isValidFebruary($year, $month, $day)) {
            throw new TomlError('invalid local date: days of February');
        }

        return new self($year, $month, $day);
    }

    public function __toString(): string
    {
        return "{$this->zeroPad($this->year, 4)}-{$this->zeroPad($this->month)}-{$this->zeroPad($this->day)}";
    }
}
