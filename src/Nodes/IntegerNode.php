<?php

declare(strict_types=1);

namespace Devium\Toml\Nodes;

/**
 * @internal
 */
final readonly class IntegerNode implements Node, NumericNode, ValuableNode
{
    public function __construct(public int $value) {}
}
