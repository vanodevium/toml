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
        return self::stringifyTable(self::toObject($input));
    }

    /**
     * Normalizes the input into stdClass tables and list arrays, so the encoder only reads
     * properties of stdClass objects. Other objects contribute what foreach sees from here:
     * public properties, or the items of a Traversable such as ArrayObject.
     */
    protected static function toObject(array|object $arrayObject): stdClass
    {
        $return = new stdClass;

        foreach ($arrayObject as $key => $value) {
            if (is_array($value) && array_is_list($value)) {
                $return->{$key} = (array) self::toObject($value);
            } elseif ($value instanceof DateTimeInterface || $value instanceof TomlDateTimeInterface) {
                $return->{$key} = $value;
            } elseif (is_array($value) || is_object($value)) {
                $return->{$key} = self::toObject($value);
            } else {
                $return->{$key} = $value;
            }
        }

        return $return;
    }

    /**
     * gettype() with "date" for date-times and lists/maps told apart as "array"/"object".
     */
    protected static function extendedTypeOf(mixed $obj): string
    {
        if ($obj instanceof TomlDateTimeInterface || $obj instanceof DateTimeInterface) {
            return 'date';
        }

        if (is_array($obj)) {
            return array_is_list($obj) ? 'array' : 'object';
        }

        return gettype($obj);
    }

    /**
     * @throws TomlError
     */
    protected static function stringifyTable(object $obj, string $prefix = ''): string
    {
        // key/value pairs go first, then sub-tables and arrays of tables
        $preamble = '';
        $tables = '';
        foreach (array_keys((array) $obj) as $k) {
            $value = $obj->{$k};
            if ($value === null) {
                continue;
            }

            $type = self::extendedTypeOf($value);
            $key = self::formatKey($k);
            if ($type === 'array' && self::isArrayOfTables($value)) {
                $tables .= self::stringifyArrayTable($value, $prefix !== '' ? "$prefix.$key" : $key);
            } elseif ($type === 'object') {
                $tblKey = $prefix !== '' ? "$prefix.$key" : $key;
                $tables .= "[$tblKey]\n";
                $tables .= self::stringifyTable($value, $tblKey);
                $tables .= "\n\n";
            } else {
                $preamble .= "$key = ".self::stringifyValue($value, $type)."\n";
            }
        }

        return trim("$preamble\n$tables");
    }

    protected static function formatKey(int|string $key): string
    {
        return preg_match(self::BARE_KEY, (string) $key) ? (string) $key : self::formatString((string) $key);
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
        $type ??= self::extendedTypeOf($value);

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

            if ($type === 'double') {
                return var_export($value, true);
            }

            return (string) $value;
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
    protected static function stringifyInlineTable(stdClass $obj): string
    {
        $pairs = [];
        foreach (array_keys((array) $obj) as $k) {
            $pairs[] = self::formatKey($k).' = '.self::stringifyValue($obj->{$k});
        }

        return $pairs === [] ? '{}' : '{ '.implode(', ', $pairs).' }';
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
