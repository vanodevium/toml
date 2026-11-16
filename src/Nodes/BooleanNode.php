<?php

declare(strict_types=1);

namespace Devium\Toml\Nodes;

/**
 * @internal
 */
final class BooleanNode implements Node, ValuableNode
{
    public function __construct(public readonly bool $value) {}
}
