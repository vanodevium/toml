<?php

namespace Devium\Toml;

final class TomlLocalDateTime extends TomlInternalDateTime
{
    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly int $day,
        public readonly int $hour,
        public readonly int $minute,
        public readonly int $second,
        public readonly int $millisecond,
    ) {}

    /**
     * @throws TomlError
     */
    public static function fromString($value): self
    {
        $components = preg_split('/[tT ]/', (string) $value);

        if (count($components) !== 2) {
            throw new TomlError("invalid local date-time format \"$value\"");
        }

        $date = TomlLocalDate::fromString($components[0]);
        $time = TomlLocalTime::fromString($components[1]);

        return new self(
            $date->year, $date->month, $date->day, $time->hour, $time->minute, $time->second, $time->millisecond
        );
    }

    public function __toString(): string
    {
        return "{$this->toDateString()}T{$this->toTimeString()}{$this->millisecondToString()}";
    }

    private function toDateString(): string
    {
        return "{$this->zeroPad($this->year, 4)}-{$this->zeroPad($this->month)}-{$this->zeroPad($this->day)}";
    }

    private function toTimeString(): string
    {
        return "{$this->zeroPad($this->hour)}:{$this->zeroPad($this->minute)}:{$this->zeroPad($this->second)}";
    }

    private function millisecondToString(): string
    {
        if ($this->millisecond === 0) {
            return '';
        }

        return ".$this->millisecond";
    }
}
