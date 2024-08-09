<?php

namespace Devium\Toml;

use stdClass;
use Throwable;

/**
 * For testing purposes.
 *
 * @internal
 */
class TomlTag
{
    public static function tagObject(mixed $obj): array|stdClass
    {
        if (is_int($obj)) {
            return ['type' => 'integer', 'value' => (string) $obj];
        }

        if (is_string($obj)) {
            if (preg_match('/^[+-]?\d+[.]\d+$/', $obj)) {
                return ['type' => 'float', 'value' => $obj];
            }

            return ['type' => 'string', 'value' => $obj];
        }

        if (is_numeric($obj)) {
            if (is_nan($obj)) {
                $obj = 'nan';
            }
            if ($obj === -INF) {
                $obj = '-inf';
            }
            if ($obj === INF) {
                $obj = 'inf';
            }

            return ['type' => 'float', 'value' => (string) $obj];
        }

        if (is_bool($obj)) {
            return ['type' => 'bool', 'value' => $obj ? 'true' : 'false'];
        }

        if ($obj instanceof TomlDateTime) {
            return ['type' => 'datetime', 'value' => (string) $obj];
        }

        if ($obj instanceof TomlLocalDate) {
            return ['type' => 'date-local', 'value' => (string) $obj];
        }

        if ($obj instanceof TomlLocalTime) {
            return ['type' => 'time-local', 'value' => (string) $obj];
        }

        if ($obj instanceof TomlLocalDateTime) {
            return ['type' => 'datetime-local', 'value' => (string) $obj];
        }

        if (is_array($obj)) {
            return array_map(static fn ($item) => self::tagObject($item), $obj);
        }

        $tagged = new stdClass;

        if ($obj instanceof stdClass) {
            $obj = get_object_vars($obj);
        }

        foreach ($obj as $key => $value) {
            $tagged->{$key} = self::tagObject($value);
        }

        return $tagged;
    }

    /**
     * @throws Throwable
     * @throws TomlError
     */
    public static function untagObject($obj)
    {
        if (is_array($obj)) {
            return array_map(static fn ($item) => self::untagObject($item), $obj);
        }

        if (count(get_object_vars($obj)) === 2 && property_exists($obj, 'type') && property_exists($obj, 'value')) {
            switch ($obj->type) {
                case 'string':
                    return $obj->value;
                case 'bool':
                    return $obj->value === 'true';
                case 'integer':
                    return (int) $obj->value;
                case 'float':
                    if ($obj->value === 'nan') {
                        return NAN;
                    }
                    if ($obj->value === '+nan') {
                        return NAN;
                    }
                    if ($obj->value === '-nan') {
                        return NAN;
                    }
                    if ($obj->value === 'inf') {
                        return INF;
                    }
                    if ($obj->value === '+inf') {
                        return INF;
                    }
                    if ($obj->value === '-inf') {
                        return -INF;
                    }

                    return (float) $obj->value;
                case 'datetime':
                    return new TomlDateTime($obj->value);
                case 'datetime-local':
                    return TomlLocalDateTime::fromString($obj->value);
                case 'date-local':
                    return TomlLocalDate::fromString($obj->value);
                case 'time-local':
                    return TomlLocalTime::fromString($obj->value);
            }

            throw new TomlError('cannot untag object');
        }

        $res = new stdClass;
        foreach ((array) $obj as $key => $value) {
            $res->{$key} = self::untagObject($value);
        }

        return $res;
    }
}
