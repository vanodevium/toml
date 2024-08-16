<?php

namespace Devium\Toml\Nodes;

/**
 * @internal
 */
final class IntegerNode implements Node
{
    public function __construct(public readonly int $value) {}
}
