<?php

namespace Devium\Toml\Nodes;

use Devium\Toml\TomlDateTime;

/**
 * @internal
 */
final class OffsetDateTimeNode implements Node, TomlDateTimeNode, ValuableNode
{
    public function __construct(public readonly TomlDateTime $value) {}
}
