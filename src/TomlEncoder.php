<?php

namespace Devium\Toml;

use stdClass;

/**
 * @internal
 */
readonly class TomlEncoder
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
        if ($obj instanceof TomlAbstractDateTime) {
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

    protected static function isArrayOfTables(array $obj): bool
    {
        foreach ($obj as $item) {
            if (self::extendedTypeOf($item) !== 'object') {
                return false;
            }
        }

        return $obj !== [];
    }

    protected static function formatString(string $s): string
    {
        return preg_replace('/\x7f/', '\\u007f', json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @throws TomlError
     */
    protected static function stringifyValue(mixed $val, ?string $type = null): string
    {
        if ($type === null) {
            $type = self::extendedTypeOf($val);
        }

        if ($type === 'integer' || $type === 'double') {
            if (is_nan($val)) {
                return 'nan';
            }
            if ($val === INF) {
                return 'inf';
            }
            if ($val === -INF) {
                return '-inf';
            }

            return (string) $val;
        }
        if ($type === 'boolean') {
            return $val ? 'true' : 'false';
        }
        if ($type === 'string') {
            return self::formatString($val);
        }
        if ($type === 'date') {
            if ($val === false) {
                throw new TomlError('cannot serialize invalid date');
            }

            return (string) $val;
        }
        if ($type === 'object') {
            return self::stringifyInlineTable($val);
        }
        if ($type === 'array') {
            return self::stringifyArray($val);
        }

        throw new TomlError('unrecognized type');
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
    protected static function stringifyTable(stdClass $obj, string $prefix = ''): string
    {
        $preamble = '';
        $tables = '';
        $keys = array_keys((array) $obj);
        foreach ($keys as $k) {
            $type = self::extendedTypeOf($obj->{$k});
            $key = preg_match(self::BARE_KEY, $k) ? $k : self::formatString($k);
            if ($type === 'array' && self::isArrayOfTables($obj->{$k})) {
                $tables .= self::stringifyArrayTable($obj->{$k}, $prefix !== '' && $prefix !== '0' ? "$prefix.$key" : $key);
            } else {
                if ($type === 'object') {
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
        }

        return trim("$preamble\n$tables");
    }
}
