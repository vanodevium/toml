<?php

declare(strict_types=1);

namespace Devium\Toml\Nodes;

use Devium\Toml\TomlLocalDateTime;

/**
 * @internal
 */
final class LocalDateTimeNode implements Node, TomlDateTimeNode, ValuableNode
{
    public function __construct(public readonly TomlLocalDateTime $value) {}
}
