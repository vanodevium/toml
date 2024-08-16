<?php

namespace Devium\Toml\Nodes;

/**
 * @internal
 */
final class StringNode implements Node, ValuableNode
{
    public function __construct(public readonly string $value) {}
}
