<?php

namespace Devium\Toml;

use DateTimeImmutable;
use Stringable;
use Throwable;

class TomlDateTime extends DateTimeImmutable implements Stringable, TomlDateTimeInterface
{
    public const REGEX = '/(\d{4})(-(0[1-9]|1[0-2])(-([12]\d|0[1-9]|3[01]))([Tt\s]((([01]\d|2[0-3])((:)[0-5]\d))(:\d+)?)?(:[0-5]\d([.]\d+)?)?([zZ]|([+-])([01]\d|2[0-3]):?([0-5]\d)?)?)?)$/';

    /**
     * @throws Throwable
     */
    public function __construct(string $dateTimeString)
    {
        if (! preg_match(self::REGEX, $dateTimeString, $matches)) {
            throw new TomlError('datetime format must have leading zero');
        }

        if (! TomlInternalDateTime::isValidFebruary($matches[1], $matches[3], $matches[5])) {
            throw new TomlError('invalid local date: days of February');
        }

        parent::__construct($dateTimeString);
    }

    public function __toString(): string
    {
        return TomlInternalDateTime::toTOMLString($this);
    }
}
