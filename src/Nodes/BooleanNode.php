<?php

namespace Devium\Toml\Nodes;

/**
 * @internal
 */
final class BooleanNode implements Node, ValuableNode
{
    public function __construct(public readonly bool $value) {}
}
