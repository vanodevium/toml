<?php

namespace Devium\Toml\Nodes;

/**
 * @internal
 */
final class FloatNode implements Node
{
    public function __construct(public readonly float|string $value) {}
}
