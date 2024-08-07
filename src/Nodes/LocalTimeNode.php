<?php

namespace Devium\Toml\Nodes;

use Devium\Toml\TomlLocalTime;

/**
 * @internal
 */
final readonly class LocalTimeNode implements Node
{
    public function __construct(public TomlLocalTime $value) {}
}
