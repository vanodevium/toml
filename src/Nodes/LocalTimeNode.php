<?php

declare(strict_types=1);

namespace Devium\Toml\Nodes;

use Devium\Toml\TomlLocalTime;

/**
 * @internal
 */
final readonly class LocalTimeNode implements Node, TomlDateTimeNode, ValuableNode
{
    public function __construct(public TomlLocalTime $value) {}
}
