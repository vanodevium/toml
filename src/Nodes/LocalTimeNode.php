<?php

namespace Devium\Toml\Nodes;

use Devium\Toml\TomlLocalTime;

/**
 * @internal
 */
final class LocalTimeNode implements Node, TomlDateTimeNode, ValuableNode
{
    public function __construct(public readonly TomlLocalTime $value) {}
}
