<?php

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
