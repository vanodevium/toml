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
                return self::merge(self::mapNormalize($node->elements()));

            case KeyNode::class:
                return self::mapNormalize($node->keys());

            case KeyValuePairNode::class:
                return self::objectify(self::normalize($node->key), self::normalize($node->value));

            case TableNode::class:
                return self::objectify(
                    self::normalize($node->key),
                    self::merge(self::mapNormalize($node->elements())),
                );

            case ArrayTableNode::class:
                return self::objectify(
                    self::normalize($node->key),
                    [self::merge(self::mapNormalize($node->elements()))],
                );

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
        $normalized = [];
        foreach ($items as $item) {
            $normalized[] = self::normalize($item);
        }

        return $normalized;
    }

    /**
     * @param  array<array|object>  $values
     *
     * @throws TomlError
     */
    protected static function merge(array $values): TomlObject
    {
        $acc = new TomlObject;
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
    protected static function mergeInto(TomlObject $acc, array|object $value): void
    {
        foreach ($value as $key => $nextValue) {
            $prevValue = $acc[$key] ?? null;

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

    protected static function isKeyValuePair(mixed $value): bool
    {
        return is_object($value) && ! $value instanceof TomlInternalDateTime;
    }

    /**
     * @param  string[]  $keys
     */
    protected static function objectify(array $keys, mixed $value): TomlObject
    {
        // build from the innermost key outwards: a.b.c = v  =>  {a: {b: {c: v}}}
        for ($i = count($keys) - 1; $i >= 0; $i--) {
            $object = new TomlObject;
            $object[$keys[$i]] = $value;
            $value = $object;
        }

        return $value;
    }
}
