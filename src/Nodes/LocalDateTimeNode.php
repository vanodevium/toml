<?php

declare(strict_types=1);

namespace Devium\Toml\Nodes;

use Devium\Toml\TomlLocalDateTime;

/**
 * @internal
 */
final readonly class LocalDateTimeNode implements Node, TomlDateTimeNode, ValuableNode
{
    public function __construct(public TomlLocalDateTime $value) {}
}
