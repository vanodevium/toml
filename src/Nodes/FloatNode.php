<?php

namespace Devium\Toml\Nodes;

/**
 * @internal
 */
final class FloatNode implements Node, NumericNode, ValuableNode
{
    public function __construct(public readonly float|string $value) {}
}
