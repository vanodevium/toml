<?php

namespace Devium\Toml\Nodes;

/**
 * @internal
 */
final class BareNode implements Node
{
    public function __construct(public readonly string $value) {}
}
