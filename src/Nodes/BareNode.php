<?php

namespace Devium\Toml\Nodes;

/**
 * @internal
 */
final readonly class BareNode implements Node
{
    public function __construct(public string $value) {}
}
