<?php

namespace Devium\Toml\Nodes;

/**
 * @internal
 */
final class BooleanNode implements Node
{
    public function __construct(public bool $value) {}
}
