<?php

namespace Devium\Toml\Nodes;

/**
 * @internal
 */
final class StringNode implements Node
{
    public function __construct(public string $value) {}
}
