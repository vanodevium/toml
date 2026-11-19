<?php

namespace Devium\Toml;

use Devium\Toml\Nodes\ArrayNode;
use Devium\Toml\Nodes\ArrayTableNode;
use Devium\Toml\Nodes\BareNode;
use Devium\Toml\Nodes\BooleanNode;
use Devium\Toml\Nodes\FloatNode;
use Devium\Toml\Nodes\InlineTableNode;
use Devium\Toml\Nodes\IntegerNode;
use Devium\Toml\Nodes\KeyNode;
use Devium\Toml\Nodes\KeyValuePairNode;
use Devium\Toml\Nodes\LocalDateNode;
use Devium\Toml\Nodes\LocalDateTimeNode;
use Devium\Toml\Nodes\LocalTimeNode;
use Devium\Toml\Nodes\OffsetDateTimeNode;
use Devium\Toml\Nodes\RootTableNode;
use Devium\Toml\Nodes\StringNode;
use Devium\Toml\Nodes\TableNode;

/**
 * @internal
 */
final class TomlNormalizer
{
    /**
     * @throws TomlError
     */
    public static function normalize(Nodes\Node $node): mixed
    {
        switch ($node::class) {
            case InlineTableNode::class:
            case RootTableNode::class:
                $elements = self::mapNormalize($node->elements());

                return self::merge(...$elements);

            case KeyNode::class:
                return self::mapNormalize($node->keys());

            case KeyValuePairNode::class:

                $key = self::normalize($node->key);
                $value = self::normalize($node->value);

                return self::objectify($key, $value);

            case TableNode::class:
                $key = self::normalize($node->key);
                $elements = self::mapNormalize($node->elements());

                return self::objectify($key, self::merge(...$elements));

            case ArrayTableNode::class:
                $key = self::normalize($node->key);
                $elements = self::mapNormalize($node->elements());

                return self::objectify($key, [self::merge(...$elements)]);

            case ArrayNode::class:
                return self::mapNormalize($node->elements());

            case OffsetDateTimeNode::class:
            case LocalDateTimeNode::class:
            case LocalDateNode::class:
            case LocalTimeNode::class:
            case BareNode::class:
            case StringNode::class:
            case IntegerNode::class:
            case FloatNode::class:
            case BooleanNode::class:
                return $node->value;

            default:
                throw new TomlError('unsupported type: '.$node::class);
        }
    }

    /**
     * @throws TomlError
     */
    protected static function mapNormalize(array $items): array
    {
        return array_map(self::normalize(...), $items);
    }

    /**
     * @throws TomlError
     */
    protected static function merge(...$values): TomlObject
    {
        $acc = new TomlObject([]);
        foreach ($values as $value) {
            self::mergeInto($acc, $value);
        }

        return $acc;
    }

    /**
     * Merges $value into $acc in place (no copying of already merged data).
     *
     * @throws TomlError
     */
    protected static function mergeInto(TomlObject $acc, $value): void
    {
        foreach ($value as $key => $nextValue) {

            $prevValue = $acc->offsetExists($key) ? $acc->offsetGet($key) : null;

            if (is_array($prevValue) && is_array($nextValue)) {
                $acc->offsetSet($key, null); // drop the storage reference so the append below does not copy
                foreach ($nextValue as $item) {
                    $prevValue[] = $item;
                }
                $acc->offsetSet($key, $prevValue);
            } elseif (self::isKeyValuePair($prevValue) && self::isKeyValuePair($nextValue)) {
                self::mergeInto($prevValue, $nextValue);
            } elseif (is_array($prevValue) &&
                self::isKeyValuePair($nextValue) &&
                self::isKeyValuePair(end($prevValue))) {
                self::mergeInto(end($prevValue), $nextValue);
            } elseif (isset($prevValue)) {
                throw new TomlError('unexpected value');
            } else {
                $acc->offsetSet($key, $nextValue);
            }
        }
    }

    protected static function isKeyValuePair($value): bool
    {
        if ($value instanceof TomlInternalDateTime) {
            return false;
        }

        return is_object($value);
    }

    /**
     * @param  string[]  $keys
     */
    protected static function objectify(array $keys, $value): TomlObject
    {
        $initialValue = new TomlObject([]);
        $object = &$initialValue;
        foreach (array_slice($keys, 0, -1) as $prop) {
            $object[$prop] = new TomlObject([]);
            $object = &$object[$prop];
        }

        $key = array_pop($keys);
        $object->{$key} = $value;

        return $initialValue;
    }
}
