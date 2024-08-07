<?php

namespace Devium\Toml\Nodes;

use Devium\Toml\TomlLocalDate;

/**
 * @internal
 */
final readonly class LocalDateNode implements Node
{
    public function __construct(public TomlLocalDate $value) {}
}
