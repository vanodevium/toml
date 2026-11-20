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

            return ['type' => 'float', 'value' => is_float($obj) ? var_export($obj, true) : (string) $obj];
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
            return array_map(self::tagObject(...), $obj);
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
    public static function untagObject($obj): mixed
    {
        if (is_array($obj)) {
            return array_map(self::untagObject(...), $obj);
        }

        if (property_exists($obj, 'type') && property_exists($obj, 'value') && count(get_object_vars($obj)) === 2) {
            return match ($obj->type) {
                'string' => $obj->value,
                'bool' => $obj->value === 'true',
                'integer' => (int) $obj->value,
                'float' => match ($obj->value) {
                    'nan', '+nan', '-nan' => NAN,
                    'inf', '+inf' => INF,
                    '-inf' => -INF,
                    default => (float) $obj->value,
                },
                'datetime' => new TomlDateTime($obj->value),
                'datetime-local' => TomlLocalDateTime::fromString($obj->value),
                'date-local' => TomlLocalDate::fromString($obj->value),
                'time-local' => TomlLocalTime::fromString($obj->value),
                default => throw new TomlError('cannot untag object'),
            };
        }

        $res = new stdClass;
        foreach ((array) $obj as $key => $value) {
            $res->{$key} = self::untagObject($value);
        }

        return $res;
    }
}
