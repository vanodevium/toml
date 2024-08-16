<?php

namespace Devium\Toml\Nodes;

use Devium\Toml\TomlDateTime;

/**
 * @internal
 */
final class OffsetDateTimeNode implements Node
{
    public function __construct(public TomlDateTime $value) {}
}
