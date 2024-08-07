<?php

namespace Devium\Toml\Nodes;

/**
 * @internal
 */
final class KeyNode implements Node
{
    /**
     * @param  BareNode[]|StringNode[]  $keys
     */
    public function __construct(private array $keys) {}

    public function addKey(BareNode|StringNode $key): void
    {
        $this->keys[] = $key;
    }

    public function keys(): array
    {
        return $this->keys;
    }
}
