<?php

declare(strict_types=1);

namespace Devium\Toml\Nodes;

/**
 * @internal
 */
final readonly class KeyValuePairNode implements Node
{
    public function __construct(
        public KeyNode $key,
        public ValuableNode $value
    ) {}
}
