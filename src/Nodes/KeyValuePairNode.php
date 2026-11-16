<?php

declare(strict_types=1);

namespace Devium\Toml\Nodes;

/**
 * @internal
 */
final class KeyValuePairNode implements Node
{
    public function __construct(
        public readonly KeyNode $key,
        public readonly ValuableNode $value
    ) {}
}
