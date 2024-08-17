<?php

namespace Devium\Toml;

use DateTimeInterface;
use stdClass;

/**
 * @internal
 */
class TomlEncoder
{
    protected const BARE_KEY = '/^[a-z0-9-_]+$/i';

    /**
     * @throws TomlError
     */
    public static function encode(array|stdClass $input): string
    {
        if (is_array($input)) {
            $input = self::toObject($input);
        }

        if (self::extendedTypeOf($input) !== 'object') {
            throw new TomlError('stringify can only be called with an object');
        }

        return self::stringifyTable($input);
    }

    protected static function toObject(array $arrayObject): array|object
    {
        $return = new stdClass;

        foreach ($arrayObject as $key => $value) {
            if (is_array($value) && array_is_list($value)) {
                $return->{$key} = (array) self::toObject($value);
            } elseif ($value instanceof DateTimeInterface) {
                $return->{$key} = $value;
            } elseif (is_array($value) || is_object($value)) {
                $return->{$key} = self::toObject($value);
            } else {
                $return->{$key} = $value;
            }
        }

        return $return;
    }

    protected static function extendedTypeOf(mixed $obj): string
    {
        if ($obj instanceof TomlDateTimeInterface) {
            return 'date';
        }

        if ($obj instanceof DateTimeInterface) {
            return 'date';
        }

        if (is_array($obj) && array_is_list($obj)) {
            return 'array';
        }

        if (is_array($obj)) {
            return 'object';
        }

        return gettype($obj);
    }

    /**
     * @throws TomlError
     */
    protected static function stringifyTable(object $obj, string $prefix = ''): string
    {
        $preamble = '';
        $tables = '';
        $keys = array_keys((array) $obj);
        foreach ($keys as $k) {

            if ($obj->{$k} === null) {
                continue;
            }

            $type = self::extendedTypeOf($obj->{$k});
            $key = preg_match(self::BARE_KEY, $k) ? $k : self::formatString($k);
            if ($type === 'array' && self::isArrayOfTables($obj->{$k})) {
                $tables .= self::stringifyArrayTable($obj->{$k}, $prefix !== '' && $prefix !== '0' ? "$prefix.$key" : $key);
            } elseif ($type === 'object') {
                $tblKey = $prefix !== '' && $prefix !== '0' ? "$prefix.$key" : $key;
                $tables .= "[$tblKey]\n";
                $tables .= self::stringifyTable($obj->{$k}, $tblKey);
                $tables .= "\n\n";
            } else {
                $preamble .= $key;
                $preamble .= ' = ';
                $preamble .= self::stringifyValue($obj->{$k}, $type);
                $preamble .= "\n";
            }
        }

        return trim("$preamble\n$tables");
    }

    protected static function formatString(string $s): string
    {
        return preg_replace('/\x7f/', '\\u007f', json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    protected static function isArrayOfTables(array $obj): bool
    {
        foreach ($obj as $item) {
            if (self::extendedTypeOf($item) !== 'object') {
                return false;
            }
        }

        return $obj !== [];
    }

    /**
     * @throws TomlError
     */
    protected static function stringifyArrayTable(array $array, string $key): string
    {
        $res = '';
        foreach ($array as $item) {
            $res .= "[[$key]]\n";
            $res .= self::stringifyTable($item, $key);
            $res .= "\n\n";
        }

        return $res;
    }

    /**
     * @throws TomlError
     */
    protected static function stringifyValue(mixed $value, ?string $type = null): string
    {
        if ($type === null) {
            $type = self::extendedTypeOf($value);
        }

        if ($type === 'integer' || $type === 'double') {
            if (is_nan($value)) {
                return 'nan';
            }
            if ($value === INF) {
                return 'inf';
            }
            if ($value === -INF) {
                return '-inf';
            }

            if ($type === 'double' && ! str_contains((string) $value, '.')) {
                $value .= '.0';
            }

            return $value;
        }

        if ($type === 'boolean') {
            return $value ? 'true' : 'false';
        }

        if ($type === 'string') {
            return self::formatString($value);
        }

        if ($type === 'date') {
            if ($value === false) {
                throw new TomlError('cannot serialize invalid date');
            }

            if ($value instanceof DateTimeInterface) {
                return TomlInternalDateTime::toTOMLString($value);
            }

            return (string) $value;
        }

        if ($type === 'object') {
            return self::stringifyInlineTable($value);
        }

        if ($type === 'array') {
            return self::stringifyArray($value);
        }

        throw new TomlError('unrecognized type: '.$type);
    }

    /**
     * @throws TomlError
     */
    protected static function stringifyInlineTable(array|stdClass $obj): string
    {
        $keys = array_keys((array) $obj);
        if ($keys === []) {
            return '{}';
        }
        $res = '{ ';
        foreach ($keys as $i => $k) {
            if ($i !== 0) {
                $res .= ', ';
            }
            $res .= preg_match(self::BARE_KEY, $k) ? $k : self::formatString($k);
            $res .= ' = ';
            $res .= self::stringifyValue($obj->{$k});
        }

        return $res.' }';
    }

    /**
     * @throws TomlError
     */
    protected static function stringifyArray(array $array): string
    {
        if ($array === []) {
            return '[]';
        }
        $res = '[ ';
        foreach ($array as $i => $value) {
            if ($i) {
                $res .= ', ';
            }
            if ($value === null) {
                throw new TomlError('arrays cannot contain null values');
            }
            $res .= self::stringifyValue($value);
        }

        return $res.' ]';
    }
}
