<?php

namespace Devium\Toml;

final class TomlLocalTime extends TomlInternalDateTime
{
    public readonly int $millisecond;

    public function __construct(
        public readonly int $hour,
        public readonly int $minute,
        public readonly int $second,
        $millisecond
    ) {
        $this->millisecond = intval(substr((string) $millisecond, 0, 3));
    }

    /**
     * @throws TomlError
     */
    public static function fromString($value): self
    {
        if (! preg_match('/^\d{2}:\d{2}:\d{2}(\.\d+)?$/', (string) $value)) {
            throw new TomlError("invalid local time format \"$value\"");
        }

        $components = explode(':', (string) $value);
        [$hour, $minute] = array_map('intval', array_slice($components, 0, 2));
        $p = array_map('intval', explode('.', $components[2]));
        $second = $p[0];
        $millisecond = $p[1] ?? 0;

        if (! self::isHour($hour) || ! self::isMinute($minute) || ! self::isSecond($second)) {
            throw new TomlError("invalid local time format \"$value\"");
        }

        return new self($hour, $minute, $second, $millisecond);
    }

    public function __toString(): string
    {
        return $this->timeToString().$this->millisecondToString();
    }

    private function timeToString(): string
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
