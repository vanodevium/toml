<?php

declare(strict_types=1);

namespace Devium\Toml\Nodes;

use Devium\Toml\TomlLocalDate;

/**
 * @internal
 */
final class LocalDateNode implements Node, TomlDateTimeNode, ValuableNode
{
    public function __construct(public readonly TomlLocalDate $value) {}
}
