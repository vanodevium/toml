<?php

namespace Devium\Toml\Nodes;

/**
 * @internal
 */
final readonly class FloatNode implements Node
{
    public function __construct(public float|string $value) {}
}
