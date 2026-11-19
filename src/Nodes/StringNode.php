<?php

declare(strict_types=1);

namespace Devium\Toml\Nodes;

/**
 * @internal
 */
final readonly class StringNode implements Node, ValuableNode
{
    public function __construct(public string $value) {}
}
