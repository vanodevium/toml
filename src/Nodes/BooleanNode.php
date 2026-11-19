<?php

declare(strict_types=1);

namespace Devium\Toml\Nodes;

/**
 * @internal
 */
final readonly class BooleanNode implements Node, ValuableNode
{
    public function __construct(public bool $value) {}
}
