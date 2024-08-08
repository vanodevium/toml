<?php

namespace Devium\Toml;

use DateTime;
use DateTimeZone;
use JsonSerializable;
use Throwable;

/**
 * @internal
 */
class TomlDateTime extends TomlAbstractDateTime implements JsonSerializable
{
    public DateTime $dt;

    /**
     * @throws Throwable
     */
    public function __construct(string $dateTimeString)
    {
        if (! preg_match('/(\d{4})(-(0[1-9]|1[0-2])(-([12]\d|0[1-9]|3[01]))([Tt\s]((([01]\d|2[0-3])((:)[0-5]\d))(:\d+)?)?(:[0-5]\d([.]\d+)?)?([zZ]|([+-])([01]\d|2[0-3]):?([0-5]\d)?)?)?)$/', $dateTimeString, $matches)) {
            throw new TomlError('datetime format must have leading zero');
        }

        if (! self::isValidFebruary($matches[1], $matches[3], $matches[5])) {
            throw new TomlError('invalid local date: days of February');
        }

        $this->dt = new DateTime($dateTimeString);
    }

    public function __toString(): string
    {
        $ms = $this->dt->format('u');

        return str_replace('!', substr($ms, 0, 3), $this->dt
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.!p'));
    }

    public function jsonSerialize(): string
    {
        return $this->__toString();
    }
}
