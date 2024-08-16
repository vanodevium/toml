<?php

namespace Devium\Toml\Nodes;

use Devium\Toml\TomlLocalDateTime;

/**
 * @internal
 */
final class LocalDateTimeNode implements Node
{
    public function __construct(public TomlLocalDateTime $value) {}
}
