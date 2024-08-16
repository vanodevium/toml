<?php

namespace Devium\Toml\Nodes;

/**
 * @internal
 */
final class IntegerNode implements Node, NumericNode, ValuableNode
{
    public function __construct(public readonly int $value) {}
}
